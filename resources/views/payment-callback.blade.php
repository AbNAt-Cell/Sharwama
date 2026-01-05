<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment {{ $status === 'success' ? 'Successful' : ($status === 'processing' ? 'Processing' : 'Failed') }}</title>
    @if(isset($redirect_url) && $redirect_url)
        <meta http-equiv="refresh" content="5;url={{ $redirect_url }}">
    @endif
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: {{ $status === 'success' ? 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' : ($status === 'processing' ? 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' : 'linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%)') }};
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
            background: {{ $status === 'success' ? 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' : ($status === 'processing' ? 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' : 'linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%)') }};
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }
        h1 { color: #333; font-size: 28px; margin-bottom: 10px; }
        p { color: #666; font-size: 16px; line-height: 1.6; margin-bottom: 20px; }
        .reference {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
            word-break: break-all;
        }
        .close-message { font-size: 14px; color: #999; margin-top: 20px; }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon">
            @if($status === 'success')
                ✓
            @elseif($status === 'processing')
                ⏳
            @else
                ✕
            @endif
        </div>
        <h1>Payment {{ $status === 'success' ? 'Successful' : ($status === 'processing' ? 'Processing' : 'Failed') }}</h1>
        <p>
            @if($status === 'success')
                Your payment has been processed successfully. Thank you!
            @elseif($status === 'processing')
                Your payment is being processed. We'll update you shortly.
            @else
                Payment failed or was cancelled. Please try again.
            @endif
        </p>

        @if(isset($reference) && $reference)
            <div class="reference">
                <strong>Reference:</strong> {{ $reference }}
            </div>
        @endif

        <p class="close-message">
            @if(isset($redirect_url) && $redirect_url)
                Redirecting you back to the app in a few seconds...
            @else
                You can now close this window.
            @endif
        </p>
    </div>

    <script>
        // === Critical Fix: Polyfill for flutter_inappwebview.callHandler on Android ===
        (function() {
            if (!window.flutter_inappwebview || !window.flutter_inappwebview.callHandler) {
                // Create a compatible callHandler that uses the internal _callHandler
                window.flutter_inappwebview = window.flutter_inappwebview || {};
                window.flutter_inappwebview.callHandler = function(handlerName, ...args) {
                    if (window.flutter_inappwebview._callHandler) {
                        const id = Date.now() + Math.random();
                        window.flutter_inappwebview._callHandler(handlerName, id, JSON.stringify(args));
                        return Promise.resolve();
                    }
                    console.warn('flutter_inappwebview bridge not available');
                    return Promise.reject('Not available');
                };
            }

            // Emit platform ready event (some pages expect this)
            const readyEvent = new Event('flutterInAppWebViewPlatformReady');
            window.dispatchEvent(readyEvent);
        })();

        // === Send result back to Flutter ===
        function sendToFlutter() {
            const result = {
                status: '{{ $status }}',
                reference: '{{ $reference ?? "" }}',
                message: '{{ $status === "success" ? "Payment successful" : ($status === "processing" ? "Payment processing" : "Payment failed") }}'
            };

            if (window.flutter_inappwebview && typeof window.flutter_inappwebview.callHandler === 'function') {
                console.log('Sending to Flutter:', result);
                window.flutter_inappwebview.callHandler('paymentCallback', result)
                    .then(() => console.log('Flutter callback success'))
                    .catch(err => console.error('Flutter callback failed:', err));
            } else {
                console.log('Flutter bridge not available, skipping callHandler');
            }
        }

        // === Auto redirect or close ===
        setTimeout(function () {
            @if(isset($redirect_url) && $redirect_url)
                console.log('Redirecting to app deep link:', '{{ $redirect_url }}');
                window.location.href = '{{ $redirect_url }}';
            @else
                sendToFlutter();

                // Try to close the WebView/tab
                setTimeout(() => {
                    try { window.close(); } catch(e) {}
                    // Fallback: notify user
                    document.querySelector('.close-message').innerText = 'Payment complete. You can close this tab.';
                }, 500);
            @endif
        }, 2000);

        // Also try sending immediately in case redirect is fast
        @if(!isset($redirect_url) || !$redirect_url)
            sendToFlutter();
        @endif
    </script>
</body>

</html>