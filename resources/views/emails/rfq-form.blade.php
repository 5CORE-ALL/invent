<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFQ Form - {{ $form->name }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 30px 20px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #333;
        }
        .message {
            font-size: 15px;
            color: #555;
            margin-bottom: 25px;
            line-height: 1.8;
        }
        .form-details {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .form-details h2 {
            margin-top: 0;
            color: #667eea;
            font-size: 20px;
        }
        .form-details p {
            margin: 10px 0;
            color: #666;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 16px;
            margin: 25px 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #e9ecef;
        }
        .email-footer p {
            margin: 5px 0;
        }
        .additional-message {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-style: italic;
            color: #856404;
        }
        .divider {
            height: 1px;
            background-color: #e9ecef;
            margin: 25px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>📋 Request for Quotation (RFQ)</h1>
        </div>
        
        <div class="email-body">
            <div class="greeting">
                <strong>Dear {{ $supplier->name ?? 'Valued Supplier' }}{{ !empty($supplier->company) ? ' (' . $supplier->company . ')' : '' }},</strong>
            </div>
            
            <div class="message">
                We hope this email finds you well. We are pleased to invite you to submit a quotation for our current procurement requirement.
            </div>
            
            @if($additionalMessage)
            <div class="additional-message">
                <strong>Additional Note:</strong><br>
                {!! nl2br(e($additionalMessage)) !!}
            </div>
            @endif
            
            <div class="form-details">
                <h2>{{ $form->title }}</h2>
                @if($form->subtitle)
                <p><strong>Description:</strong> {{ $form->subtitle }}</p>
                @endif
                <p><strong>Form Name:</strong> {{ $form->name }}</p>
            </div>
            
            <div class="message">
                Please click the button below to access the RFQ form and submit your quotation. We kindly request that you complete the form with accurate and detailed information.
            </div>
            
            <div class="button-container">
                <a href="{{ $formUrl }}" class="cta-button" target="_blank">
                    📝 Access RFQ Form
                </a>
            </div>
            
            <div class="divider"></div>
            
            <div class="message">
                <strong>Important Notes:</strong>
                <ul style="color: #666; line-height: 1.8;">
                    <li>Please ensure all required fields are completed accurately</li>
                    <li>Submit your quotation before the deadline</li>
                    <li>If you have any questions, please feel free to contact us</li>
                </ul>
            </div>
            
            <div class="message">
                We appreciate your interest and look forward to receiving your quotation.
            </div>
            
            <div class="message">
                Best regards,<br>
                <strong>5Core Management</strong><br>
                Purchase Department
            </div>
        </div>
        
        <div class="email-footer">
            <p><strong>5 Core Management</strong></p>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p style="font-size: 12px; color: #999; margin-top: 10px;">
                If you have any questions, please contact our purchase department.
            </p>
        </div>
    </div>
</body>
</html>
