/**
 * Inventory Management - Google Sheets Export Service
 *
 * doPost = CREATE a brand-new Google Spreadsheet (does NOT touch the 4-sheet file)
 * doGet  = READ one of those existing 4 sheets as JSON (?sheet=Sheet1)
 *
 * Paste into the Apps Script that already has Drive permission.
 * Deploy → Manage deployments → New version.
 *
 * FIXED_SPREADSHEET_ID is ONLY for doGet (the workbook that already has 4 sheets).
 */
var FIXED_SPREADSHEET_ID = ''; // only for doGet — the 4-sheet workbook ID

function getSpreadsheet_(requestedId) {
  var id = String(requestedId || FIXED_SPREADSHEET_ID || '').trim();
  if (id && id !== 'Sheet1') {
    return SpreadsheetApp.openById(id);
  }

  var active = SpreadsheetApp.getActiveSpreadsheet();
  if (active) {
    return active;
  }

  throw new Error(
    'Set FIXED_SPREADSHEET_ID in this script (the file that already has 4 sheets), '
    + 'or set GOOGLE_SHEETS_VERIFICATION_ADJUSTMENT_ID in Laravel .env'
  );
}

function uniqueSheetName_(ss, base) {
  var safe = String(base || 'Verification Adjustment').substring(0, 80);
  var name = safe;
  var n = 1;
  while (ss.getSheetByName(name)) {
    n++;
    name = safe + ' (' + n + ')';
  }
  return name;
}

function writeRowsToSheet_(sheet, rows) {
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

function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var rows = data.data || [];
    var sheetTitle = data.sheetTitle || 'Verification Adjustment';
    var shareEmails = data.shareEmails || [];
    var shareDomain = data.shareDomain || '5core.com';
    var shareRole = data.shareRole || 'writer';
    var shareAnyone = data.shareAnyone !== false;
    var shareAnyoneRole = data.shareAnyoneRole || 'writer';

    if (rows.length === 0) {
      return jsonOut_({
        success: false,
        message: 'No data provided'
      });
    }

    var timestamp = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd HH:mm:ss');
    var spreadsheet = SpreadsheetApp.create(sheetTitle + ' - ' + timestamp);
    var sheet = spreadsheet.getSheets()[0];
    sheet.setName(sheetTitle);
    writeRowsToSheet_(sheet, rows);

    Logger.log('Created NEW spreadsheet: ' + spreadsheet.getId());

    var shareResult = ensureShareAccess_(spreadsheet.getId(), {
      shareEmails: shareEmails,
      shareDomain: shareDomain,
      shareRole: shareRole,
      shareAnyone: shareAnyone,
      shareAnyoneRole: shareAnyoneRole
    });

    return jsonOut_({
      success: true,
      message: 'New Google Sheet created',
      spreadsheetId: spreadsheet.getId(),
      sheetName: sheet.getName(),
      sheetId: sheet.getSheetId(),
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
 * Return a sheet tab as JSON.
 * ?sheet=Sheet1
 * ?range=A1:Z
 * ?id=SPREADSHEET_ID  (optional override)
 */
function doGet(e) {
  try {
    var requestedId = (e && e.parameter && e.parameter.id) ? e.parameter.id : '';
    var sheetName = (e && e.parameter && e.parameter.sheet) ? e.parameter.sheet : 'Sheet1';
    var rangeParam = (e && e.parameter && e.parameter.range) ? e.parameter.range : null;

    var ss = getSpreadsheet_(requestedId);
    var sheet = ss.getSheetByName(sheetName);
    if (!sheet) {
      return jsonOut_({ error: 'Sheet not found: ' + sheetName });
    }

    var dataRange = rangeParam ? sheet.getRange(rangeParam) : sheet.getDataRange();
    var values = dataRange.getValues();
    if (values.length === 0) {
      return jsonOut_([]);
    }

    var headers = values[0].map(function (h) {
      return (h === null || h === undefined) ? '' : String(h).trim();
    });

    var out = [];
    for (var r = 1; r < values.length; r++) {
      var row = values[r];
      var obj = {};
      for (var c = 0; c < headers.length; c++) {
        var key = headers[c] || ('col' + (c + 1));
        var cell = row[c];
        obj[key] = (cell === '' || cell === null || cell === undefined) ? '' : cell;
      }
      out.push(obj);
    }

    return jsonOut_(out);
  } catch (err) {
    return jsonOut_({
      error: 'exception',
      message: err.message,
      stack: err.stack ? String(err.stack).substring(0, 2000) : null
    });
  }
}

/**
 * Anyone with the link can open/edit the workbook.
 * @5core.com also gets Editor when Workspace allows domain sharing.
 */
function ensureShareAccess_(fileId, opts) {
  opts = opts || {};
  var shareEmails = opts.shareEmails || [];
  var shareDomain = opts.shareDomain || '5core.com';
  var shareRole = opts.shareRole || 'writer';
  var shareAnyone = opts.shareAnyone !== false;
  var shareAnyoneRole = opts.shareAnyoneRole || 'writer';

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
    shareAnyoneRole: 'writer'
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
    shareAnyoneRole: 'writer'
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
 * Run this ONCE from the Apps Script editor after a failed share.
 */
function forceShareLatestSheet() {
  var spreadsheet = getSpreadsheet_('');
  var result = ensureShareAccess_(spreadsheet.getId(), {
    shareEmails: [
      'inventory@5core.com',
      'president@5core.com'
    ],
    shareDomain: '5core.com',
    shareRole: 'writer',
    shareAnyone: true,
    shareAnyoneRole: 'writer'
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
        sheetTitle: 'Verification Adjustment',
        spreadsheetId: FIXED_SPREADSHEET_ID,
        shareAnyone: true,
        shareAnyoneRole: 'writer',
        shareDomain: '5core.com',
        shareRole: 'writer',
        shareEmails: ['inventory@5core.com', 'president@5core.com']
      })
    }
  };

  Logger.log(doPost(testData).getContent());
}
