<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment {{ $status === 'success' ? 'Successful' : 'Failed' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: {{ $status === 'success' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' }};
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .icon {
            width: 80px;
            height: 80px;
            background: {{ $status === 'success' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' }};
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .reference {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
            word-break: break-all;
        }
        .close-message {
            font-size: 14px;
            color: #999;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            @if($status === 'success')
                ✓
            @else
                ✕
            @endif
        </div>
        <h1>Payment {{ $status === 'success' ? 'Successful' : 'Failed' }}</h1>
        <p>
            @if($status === 'success')
                Your payment has been processed successfully.
            @else
                We couldn't process your payment. Please try again.
            @endif
        </p>
        
        @if(isset($reference))
        <div class="reference">
            <strong>Reference:</strong> {{ $reference }}
        </div>
        @endif

        <p class="close-message">You can close this window now.</p>
    </div>

    <script>
        // Auto-close for mobile apps after 3 seconds
        setTimeout(function() {
            if (window.flutter_inappwebview) {
                window.flutter_inappwebview.callHandler('paymentCallback', {
                    status: '{{ $status }}',
                    reference: '{{ $reference ?? '' }}'
                });
            }
            // Try to close the window
            window.close();
        }, 3000);
    </script>
</body>
</html>
