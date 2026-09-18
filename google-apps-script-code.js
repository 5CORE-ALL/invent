/**
 * Inventory Management - Google Sheets Export Service
 *
 * New sheets are Anyone with the link (Viewer) so non-5core people can open them.
 * @5core.com accounts still get Editor when Workspace allows it.
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
    var shareDomain = data.shareDomain || '5core.com';
    var shareRole = data.shareRole || 'writer';
    var shareAnyone = data.shareAnyone !== false;
    var shareAnyoneRole = data.shareAnyoneRole || 'reader';

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

    var shareResult = ensureShareAccess_(spreadsheet.getId(), {
      shareEmails: shareEmails,
      shareDomain: shareDomain,
      shareRole: shareRole,
      shareAnyone: shareAnyone,
      shareAnyoneRole: shareAnyoneRole
    });

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
 * Anyone with the link can open the sheet (Viewer by default).
 * @5core.com still gets Editor when Workspace allows domain sharing.
 */
function ensureShareAccess_(fileId, opts) {
  opts = opts || {};
  var shareEmails = opts.shareEmails || [];
  var shareDomain = opts.shareDomain || '5core.com';
  var shareRole = opts.shareRole || 'writer';
  var shareAnyone = opts.shareAnyone !== false;
  var shareAnyoneRole = opts.shareAnyoneRole || 'reader';

  var result = {
    domainShared: false,
    anyoneWithLink: false,
    editorsAdded: [],
    errors: []
  };

  var domainRes = createDrivePermission_(fileId, {
    type: 'domain',
    role: shareRole,
    domain: shareDomain,
    allowFileDiscovery: true
  });

  if (domainRes.ok) {
    result.domainShared = true;
  } else {
    result.errors.push('domain-api: ' + domainRes.error);
    addEditorsByEmail_(fileId, shareEmails, shareDomain, result);
  }

  // Public link last. Do not use DriveApp.setSharing(DOMAIN) here —
  // that replaces permissions and would lock out non-5core people.
  if (shareAnyone) {
    shareAnyoneWithLink_(fileId, shareAnyoneRole, result);
  }

  Logger.log('Share result: ' + JSON.stringify(result));
  return result;
}

function shareAnyoneWithLink_(fileId, role, result) {
  var driveRole = role === 'writer' ? 'writer' : (role === 'commenter' ? 'commenter' : 'reader');
  var anyoneRes = createDrivePermission_(fileId, {
    type: 'anyone',
    role: driveRole
  });

  if (anyoneRes.ok) {
    result.anyoneWithLink = true;
    return;
  }

  result.errors.push('anyone-api: ' + anyoneRes.error);

  var drivePermission = driveRole === 'writer'
    ? DriveApp.Permission.EDIT
    : DriveApp.Permission.VIEW;

  try {
    DriveApp.getFileById(fileId).setSharing(
      DriveApp.Access.ANYONE_WITH_LINK,
      drivePermission
    );
    result.anyoneWithLink = true;
  } catch (e1) {
    result.errors.push('DriveApp.ANYONE_WITH_LINK: ' + e1.toString());
  }
}

/** @deprecated Use ensureShareAccess_ */
function ensureDomainEditAccess_(fileId, shareEmails, shareDomain, shareRole) {
  return ensureShareAccess_(fileId, {
    shareEmails: shareEmails,
    shareDomain: shareDomain,
    shareRole: shareRole,
    shareAnyone: true,
    shareAnyoneRole: 'reader'
  });
}

function addEditorsByEmail_(fileId, shareEmails, shareDomain, result) {
  var domainSuffix = '@' + String(shareDomain || '5core.com').toLowerCase();
  var emails = shareEmails && shareEmails.length ? shareEmails : [];

  for (var i = 0; i < emails.length; i++) {
    var email = String(emails[i] || '').trim().toLowerCase();
    if (!email || email.indexOf('@') === -1) continue;
    if (email.slice(-domainSuffix.length) !== domainSuffix) continue;

    var userRes = createDrivePermission_(fileId, {
      type: 'user',
      role: 'writer',
      emailAddress: email
    }, false);

    if (userRes.ok) {
      result.editorsAdded.push(email);
    } else if (String(userRes.error || '').indexOf('alreadyExists') === -1) {
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
}

/** @deprecated Use ensureShareAccess_ */
function ensureOpenAccess_(fileId, shareEmails) {
  return ensureShareAccess_(fileId, {
    shareEmails: shareEmails,
    shareDomain: '5core.com',
    shareRole: 'writer',
    shareAnyone: true,
    shareAnyoneRole: 'reader'
  });
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
  var result = ensureShareAccess_(SHEET_ID, {
    shareEmails: [
      'inventory@5core.com',
      'president@5core.com'
    ],
    shareDomain: '5core.com',
    shareRole: 'writer',
    shareAnyone: true,
    shareAnyoneRole: 'reader'
  });
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
        shareAnyone: true,
        shareAnyoneRole: 'reader',
        shareDomain: '5core.com',
        shareRole: 'writer',
        shareEmails: ['inventory@5core.com', 'president@5core.com']
      })
    }
  };

  Logger.log(doPost(testData).getContent());
}
