/**
 * Inventory Management - Google Sheets Export Service
 * This script receives data from your Laravel app and writes it to Google Sheets
 *
 * IMPORTANT: After editing, redeploy the Apps Script Web App
 * (Deploy → Manage deployments → Edit → New version → Deploy)
 */

function doPost(e) {
  try {
    // Parse the incoming JSON data
    var data = JSON.parse(e.postData.contents);
    var rows = data.data || [];
    var sheetTitle = data.sheetTitle || 'Sheet1';
    var spreadsheetId = data.spreadsheetId || '';
    var shareEmails = data.shareEmails || [];
    
    if (rows.length === 0) {
      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: 'No data provided'
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    var spreadsheet;
    var sheet;
    
    // Use existing spreadsheet or create new one
    if (spreadsheetId && spreadsheetId !== '') {
      try {
        spreadsheet = SpreadsheetApp.openById(spreadsheetId);
        sheet = spreadsheet.getSheets()[0];
        
        // Clear existing data
        sheet.clear();
        Logger.log('Using existing spreadsheet: ' + spreadsheetId);
      } catch (error) {
        Logger.log('Could not open existing spreadsheet, creating new one: ' + error);
        spreadsheet = null;
      }
    }
    
    // Create new spreadsheet if needed
    if (!spreadsheet) {
      var timestamp = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd HH:mm:ss');
      spreadsheet = SpreadsheetApp.create('Verification Adjustment - ' + timestamp);
      sheet = spreadsheet.getSheets()[0];
      sheet.setName(sheetTitle);
      Logger.log('Created new spreadsheet: ' + spreadsheet.getId());
    }
    
    // Convert data to 2D array
    var headers = Object.keys(rows[0]);
    var values = [headers]; // First row is headers
    
    for (var i = 0; i < rows.length; i++) {
      var row = [];
      for (var j = 0; j < headers.length; j++) {
        var value = rows[i][headers[j]];
        row.push(value !== null && value !== undefined ? value : '');
      }
      values.push(row);
    }
    
    // Write data to sheet
    if (values.length > 0) {
      var range = sheet.getRange(1, 1, values.length, headers.length);
      range.setValues(values);
      
      // Format header row
      var headerRange = sheet.getRange(1, 1, 1, headers.length);
      headerRange.setFontWeight('bold');
      headerRange.setBackground('#f3f3f3');
      
      // Freeze header row
      sheet.setFrozenRows(1);
      
      // Auto-resize columns
      for (var col = 1; col <= headers.length; col++) {
        sheet.autoResizeColumn(col);
      }
      
      Logger.log('Wrote ' + rows.length + ' rows to sheet');
    }

    // Permanent open access: anyone with the link can edit (all emails can open)
    ensureOpenAccess(spreadsheet, shareEmails);
    
    // Return success response
    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      message: 'Data exported successfully',
      spreadsheetId: spreadsheet.getId(),
      spreadsheetUrl: spreadsheet.getUrl(),
      rowsWritten: rows.length
    })).setMimeType(ContentService.MimeType.JSON);
    
  } catch (error) {
    Logger.log('Error: ' + error.toString());
    return ContentService.createTextOutput(JSON.stringify({
      success: false,
      message: 'Error: ' + error.toString()
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

/**
 * Make sheet openable for every email:
 * 1) Anyone with the link can edit
 * 2) Also add any explicit emails as editors (covers Workspace policies that block link sharing)
 */
function ensureOpenAccess(spreadsheet, shareEmails) {
  var file = DriveApp.getFileById(spreadsheet.getId());

  try {
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.EDIT);
    Logger.log('Sharing set to Anyone with the link (edit)');
  } catch (linkError) {
    // Some Workspace orgs block "anyone with link" — fall back to domain link
    Logger.log('Anyone-with-link failed, trying domain: ' + linkError);
    try {
      file.setSharing(DriveApp.Access.DOMAIN_WITH_LINK, DriveApp.Permission.EDIT);
      Logger.log('Sharing set to Domain with the link (edit)');
    } catch (domainError) {
      Logger.log('Domain sharing also failed: ' + domainError);
    }
  }

  if (shareEmails && shareEmails.length) {
    for (var i = 0; i < shareEmails.length; i++) {
      var email = String(shareEmails[i] || '').trim().toLowerCase();
      if (!email) continue;
      try {
        file.addEditor(email);
        Logger.log('Added editor: ' + email);
      } catch (emailError) {
        Logger.log('Could not add editor ' + email + ': ' + emailError);
      }
    }
  }
}

// Test function (optional - for debugging)
function testDoPost() {
  var testData = {
    postData: {
      contents: JSON.stringify({
        data: [
          { Parent: 'TEST-PARENT', SKU: 'TEST-001', INV: 10, L30: 5 },
          { Parent: 'TEST-PARENT', SKU: 'TEST-002', INV: 20, L30: 15 }
        ],
        sheetTitle: 'Test Sheet',
        spreadsheetId: '', // Leave empty to create new
        shareEmails: ['inventory@5core.com', 'ritu.kaur013@gmail.com']
      })
    }
  };
  
  var response = doPost(testData);
  Logger.log(response.getContent());
}
