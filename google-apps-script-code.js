/**
 * Inventory Management - Google Sheets Export Service
 *
 * CRITICAL: Editing this file in Laravel does NOTHING until you paste it into
 * Google Apps Script and redeploy:
 *   1. Open https://script.google.com → your Verification Adjustment export project
 *   2. Replace ALL code with this file
 *   3. Deploy → Manage deployments → pencil → Version = New version → Deploy
 *   4. Click "Review permissions" / Allow if prompted (needs Drive access)
 */

function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var rows = data.data || [];
    var sheetTitle = data.sheetTitle || 'Sheet1';
    var spreadsheetId = data.spreadsheetId || '';
    var shareEmails = data.shareEmails || [];

    if (rows.length === 0) {
      return jsonOut_({
        success: false,
        message: 'No data provided'
      });
    }

    var spreadsheet;
    var sheet;

    if (spreadsheetId && spreadsheetId !== '') {
      try {
        spreadsheet = SpreadsheetApp.openById(spreadsheetId);
        sheet = spreadsheet.getSheets()[0];
        sheet.clear();
        Logger.log('Using existing spreadsheet: ' + spreadsheetId);
      } catch (error) {
        Logger.log('Could not open existing spreadsheet, creating new one: ' + error);
        spreadsheet = null;
      }
    }

    if (!spreadsheet) {
      var timestamp = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd HH:mm:ss');
      spreadsheet = SpreadsheetApp.create('Verification Adjustment - ' + timestamp);
      sheet = spreadsheet.getSheets()[0];
      sheet.setName(sheetTitle);
      Logger.log('Created new spreadsheet: ' + spreadsheet.getId());
    }

    var headers = Object.keys(rows[0]);
    var values = [headers];

    for (var i = 0; i < rows.length; i++) {
      var row = [];
      for (var j = 0; j < headers.length; j++) {
        var value = rows[i][headers[j]];
        row.push(value !== null && value !== undefined ? value : '');
      }
      values.push(row);
    }

    if (values.length > 0) {
      var range = sheet.getRange(1, 1, values.length, headers.length);
      range.setValues(values);

      var headerRange = sheet.getRange(1, 1, 1, headers.length);
      headerRange.setFontWeight('bold');
      headerRange.setBackground('#f3f3f3');
      sheet.setFrozenRows(1);

      for (var col = 1; col <= headers.length; col++) {
        sheet.autoResizeColumn(col);
      }
    }

    var shareResult = ensureOpenAccess_(spreadsheet.getId(), shareEmails);

    return jsonOut_({
      success: true,
      message: 'Data exported successfully',
      spreadsheetId: spreadsheet.getId(),
      spreadsheetUrl: spreadsheet.getUrl(),
      rowsWritten: rows.length,
      sharing: shareResult
    });
  } catch (error) {
    Logger.log('Error: ' + error.toString());
    return jsonOut_({
      success: false,
      message: 'Error: ' + error.toString()
    });
  }
}

/**
 * Make sheet openable for every Google account.
 * Uses Drive REST API (more reliable than DriveApp.setSharing on Workspace).
 */
function ensureOpenAccess_(fileId, shareEmails) {
  var result = {
    anyoneWithLink: false,
    editorsAdded: [],
    errors: []
  };

  // 1) Anyone with the link can edit (works for ALL emails, including Gmail)
  var anyoneRes = createDrivePermission_(fileId, {
    type: 'anyone',
    role: 'writer'
  });
  if (anyoneRes.ok) {
    result.anyoneWithLink = true;
  } else {
    result.errors.push('anyone: ' + anyoneRes.error);

    // Fallback: domain with link (Workspace only)
    var domainRes = createDrivePermission_(fileId, {
      type: 'domain',
      role: 'writer',
      domain: '5core.com',
      allowFileDiscovery: false
    });
    if (!domainRes.ok) {
      result.errors.push('domain: ' + domainRes.error);
    }
  }

  // 2) Also add explicit emails as editors
  var emails = shareEmails && shareEmails.length
    ? shareEmails
    : ['inventory@5core.com', 'ritu.kaur013@gmail.com', 'president@5core.com'];

  for (var i = 0; i < emails.length; i++) {
    var email = String(emails[i] || '').trim().toLowerCase();
    if (!email || email.indexOf('@') === -1) continue;

    var userRes = createDrivePermission_(fileId, {
      type: 'user',
      role: 'writer',
      emailAddress: email
    }, true);

    if (userRes.ok) {
      result.editorsAdded.push(email);
    } else if (String(userRes.error || '').indexOf('alreadyExists') === -1) {
      // also try DriveApp fallback
      try {
        DriveApp.getFileById(fileId).addEditor(email);
        result.editorsAdded.push(email);
      } catch (e2) {
        result.errors.push(email + ': ' + (userRes.error || e2.toString()));
      }
    } else {
      result.editorsAdded.push(email);
    }
  }

  // 3) Last DriveApp fallback for link sharing
  if (!result.anyoneWithLink) {
    try {
      DriveApp.getFileById(fileId).setSharing(
        DriveApp.Access.ANYONE_WITH_LINK,
        DriveApp.Permission.EDIT
      );
      result.anyoneWithLink = true;
    } catch (e3) {
      result.errors.push('DriveApp.setSharing: ' + e3.toString());
    }
  }

  Logger.log('Share result: ' + JSON.stringify(result));
  return result;
}

function createDrivePermission_(fileId, permission, sendEmail) {
  try {
    var url = 'https://www.googleapis.com/drive/v3/permissions'
      + '?supportsAllDrives=true'
      + '&sendNotificationEmail=' + (sendEmail ? 'true' : 'false');

    var response = UrlFetchApp.fetch(url, {
      method: 'post',
      contentType: 'application/json',
      payload: JSON.stringify(permission),
      headers: {
        Authorization: 'Bearer ' + ScriptApp.getOAuthToken()
      },
      muteHttpExceptions: true
    });

    var code = response.getResponseCode();
    var body = response.getContentText();

    if (code >= 200 && code < 300) {
      return { ok: true, body: body };
    }

    // Treat "already exists" as success
    if (body.indexOf('alreadyExists') !== -1) {
      return { ok: true, body: body };
    }

    return { ok: false, error: code + ' ' + body };
  } catch (err) {
    return { ok: false, error: err.toString() };
  }
}

function jsonOut_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * Run this ONCE from the Apps Script editor (Select function → Run)
 * after a failed export, to force-share the latest sheet.
 * Paste the spreadsheet ID from the export URL into SHEET_ID below.
 */
function forceShareLatestSheet() {
  var SHEET_ID = 'PASTE_SPREADSHEET_ID_HERE'; // e.g. 1b48kfSf3ZzEMIGcXWiX1830wB7blssahVq0ei9WpuUQ
  var result = ensureOpenAccess_(SHEET_ID, [
    'inventory@5core.com',
    'ritu.kaur013@gmail.com',
    'president@5core.com'
  ]);
  Logger.log(JSON.stringify(result, null, 2));
}

function testDoPost() {
  var testData = {
    postData: {
      contents: JSON.stringify({
        data: [
          { Parent: 'TEST-PARENT', SKU: 'TEST-001', INV: 10, L30: 5 },
          { Parent: 'TEST-PARENT', SKU: 'TEST-002', INV: 20, L30: 15 }
        ],
        sheetTitle: 'Test Sheet',
        spreadsheetId: '',
        shareEmails: ['inventory@5core.com', 'ritu.kaur013@gmail.com']
      })
    }
  };

  Logger.log(doPost(testData).getContent());
}
