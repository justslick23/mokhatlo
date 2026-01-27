<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Member Joined {{ $society->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .email-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .email-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .email-body {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #333;
        }

        .greeting strong {
            color: #667eea;
        }

        .intro-text {
            font-size: 15px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.8;
        }

        /* Member Details Table */
        .member-card {
            background: #f0f4f8;
            border-left: 4px solid #667eea;
            padding: 25px;
            margin: 30px 0;
            border-radius: 6px;
            overflow-x: auto;
        }

        .member-card h3 {
            color: #333;
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .member-details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .member-details-table tr {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .member-details-table tr:last-child {
            border-bottom: none;
        }

        .member-details-table td {
            padding: 12px 0;
            vertical-align: middle;
        }

        .member-details-table td:first-child {
            font-weight: 600;
            color: #555;
            width: 100px;
            white-space: nowrap;
        }

        .member-details-table td:last-child {
            color: #333;
            padding-left: 20px;
        }

        .role-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        /* Next Steps Section */
        .next-steps {
            background-color: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 25px;
            margin: 30px 0;
            border-radius: 6px;
        }

        .next-steps h4 {
            color: #047857;
            margin-bottom: 18px;
            font-size: 16px;
            font-weight: 600;
        }

        .next-steps ul {
            list-style: none;
            padding-left: 0;
        }

        .next-steps li {
            color: #059669;
            margin-bottom: 10px;
            padding-left: 26px;
            position: relative;
            font-size: 14px;
            line-height: 1.6;
        }

        .next-steps li::before {
            content: "✓";
            position: absolute;
            left: 0;
            font-weight: bold;
            color: #10b981;
            font-size: 16px;
        }

        /* Highlight/Tip Section */
        .highlight {
            background-color: #fffbea;
            border-left: 4px solid #fbbf24;
            padding: 20px;
            margin: 30px 0;
            border-radius: 6px;
        }

        .highlight p {
            color: #78350f;
            font-size: 14px;
            margin: 0;
            line-height: 1.6;
        }

        .highlight strong {
            color: #92400e;
            font-weight: 600;
        }

        /* CTA Button */
        .cta-section {
            text-align: center;
            margin: 35px 0;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 40px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        /* Closing Text */
        .closing-text {
            font-size: 15px;
            color: #666;
            margin: 30px 0;
            line-height: 1.8;
        }

        .society-name {
            color: #667eea;
            font-weight: 600;
        }

        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 25px 0;
        }

        /* Footer */
        .email-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #e5e7eb;
            padding: 25px 30px;
            text-align: center;
            color: #666;
            font-size: 13px;
        }

        .footer-content {
            margin-bottom: 15px;
        }

        .footer-content strong {
            color: #333;
        }

        /* Footer disclaimer */
        .footer-disclaimer {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
            }

            .email-header {
                padding: 30px 20px;
            }

            .email-header h1 {
                font-size: 24px;
            }

            .email-body {
                padding: 25px 20px;
            }

            .member-card {
                padding: 20px;
            }

            .member-details-table td:first-child {
                width: 80px;
                font-size: 14px;
            }

            .member-details-table td:last-child {
                padding-left: 15px;
                font-size: 14px;
            }

            .role-badge {
                padding: 5px 12px;
                font-size: 12px;
            }

            .next-steps,
            .highlight {
                padding: 15px;
            }

            .cta-button {
                padding: 12px 30px;
                font-size: 14px;
            }

            .closing-text,
            .intro-text {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>New Member Joined!</h1>
            <p>{{ $society->name }}</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <!-- Greeting -->
            <div class="greeting">
                Hello <strong>{{ $user->name }}</strong>,
            </div>

            <!-- Intro Text -->
            <div class="intro-text">
                We're pleased to inform you that a new member has joined <span class="society-name">{{ $society->name }}</span>. 
                Please take a moment to welcome them and help them integrate into our community.
            </div>

            <!-- New Member Card -->
            <div class="member-card">
                <h3>📋 New Member Details</h3>
                <table class="member-details-table">
                    <tr>
                        <td>Name:</td>
                        <td><strong>{{ $newMember->user->name }}</strong></td>
                    </tr>
                    <tr>
                        <td>Email:</td>
                        <td>{{ $newMember->user->email }}</td>
                    </tr>
                    <tr>
                        <td>Role:</td>
                        <td>
                            <span class="role-badge">{{ ucfirst($newMember->role) }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>Join Date:</td>
                        <td>{{ $newMember->joined_date->format('d F Y') }}</td>
                    </tr>
                </table>
            </div>

            <!-- Next Steps Section -->
            <div class="next-steps">
                <h4>How You Can Help:</h4>
                <ul>
                    <li>Welcome <strong>{{ $newMember->user->name }}</strong> warmly</li>
                    <li>Help them understand society operations and culture</li>
                    <li>Include them in group communications and channels</li>
                    <li>Invite them to upcoming activities and meetings</li>
                </ul>
            </div>

            <!-- Highlight/Tip Box -->
            <div class="highlight">
                <p>
                    <strong>💡 Tip:</strong> Reach out to <strong>{{ $newMember->user->name }}</strong> to introduce yourself and offer support during their onboarding. A warm welcome goes a long way!
                </p>
            </div>

            <!-- CTA Button -->
            <div class="cta-section">
                <a href="{{ route('societies.members.index', $society) }}" class="cta-button">
                    View All Members
                </a>
            </div>

            <!-- Closing Text -->
            <div class="closing-text">
                Thank you for being an active and welcoming member of <span class="society-name">{{ $society->name }}</span>. 
                Together, we create a strong and supportive community! 🌟
            </div>

            <!-- Divider -->
            <div class="divider"></div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <div class="footer-content">
                <strong>{{ $society->name }}</strong><br>
                Powered by <strong>Mokhatlo</strong>
            </div>
            <div class="footer-disclaimer">
                © {{ now()->year }} All rights reserved. This is an automated notification from Mokhatlo.
            </div>
        </div>
    </div>
</body>
</html>