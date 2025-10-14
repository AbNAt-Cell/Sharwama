<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monnify Payment - {{ config('app.name') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .payment-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .amount {
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
            margin: 30px 0;
        }
        .currency {
            font-size: 24px;
            color: #999;
        }
        .customer-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-size: 14px;
        }
        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        .pay-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .pay-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .pay-button:active {
            transform: translateY(0);
        }
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #666;
            font-size: 12px;
            margin-top: 20px;
        }
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .processing {
            display: none;
            color: #667eea;
            font-weight: 600;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="logo">M</div>
        <h1>Monnify Payment</h1>
        <p style="color: #666; margin-bottom: 10px;">Secure Payment Gateway</p>

        <div class="amount">
            <span class="currency">{{ $data->currency_code }}</span>
            {{ number_format($data->payment_amount, 2) }}
        </div>

        <div class="customer-info">
            <div class="info-row">
                <span class="info-label">Customer Name</span>
                <span class="info-value">{{ $payer->name ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ $payer->email ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Reference</span>
                <span class="info-value">{{ $reference }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment ID</span>
                <span class="info-value">{{ $data->id }}</span>
            </div>
        </div>

        <form id="payment-form" method="POST" action="{{ route('monnify.initialize') }}">
            @csrf
            <input type="hidden" name="payment_id" value="{{ $data->id }}">
            
            <div class="loader" id="loader"></div>
            <p class="processing" id="processing-text">Processing your payment...</p>
            
            <button type="submit" class="pay-button" id="pay-btn">
                <span id="button-text">Proceed to Payment</span>
            </button>
        </form>

        <div class="secure-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1Z" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Secured by Monnify
        </div>
    </div>

    <script>
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            const btn = document.getElementById('pay-btn');
            const buttonText = document.getElementById('button-text');
            const loader = document.getElementById('loader');
            const processingText = document.getElementById('processing-text');
            
            btn.disabled = true;
            buttonText.style.display = 'none';
            loader.style.display = 'block';
            processingText.style.display = 'block';
        });
    </script>
</body>
</html>
