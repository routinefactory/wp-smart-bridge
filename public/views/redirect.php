<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>리다이렉트 중...</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .redirect-container {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            margin: 20px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e0e0e0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-message {
            font-size: 18px;
            color: #333;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .countdown {
            font-size: 14px;
            color: #666;
        }

        .countdown span {
            font-weight: bold;
            color: #667eea;
        }

        .skip-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #667eea;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .skip-link:hover {
            background: #5a67d8;
        }
    </style>
</head>

<body>
    <div class="redirect-container">
        <div class="spinner"></div>
        <div class="loading-message">
            <?php echo wp_kses_post($loading_message); ?>
        </div>
        <div class="countdown">
            <span id="countdown">
                <?php echo intval($delay); ?>
            </span>초 후 이동합니다...
        </div>
        <a href="<?php echo esc_url($target_url); ?>" class="skip-link">
            바로 이동
        </a>
    </div>

    <script>
        (function () {
            var seconds = <?php echo intval($delay); ?>;
            var countdown = document.getElementById('countdown');
            var targetUrl = <?php echo json_encode($target_url); ?>;

            function updateCountdown() {
                seconds--;
                if (seconds <= 0) {
                    window.location.href = targetUrl;
                } else {
                    countdown.textContent = seconds;
                    setTimeout(updateCountdown, 1000);
                }
            }

            if (seconds > 0) {
                setTimeout(updateCountdown, 1000);
            } else {
                window.location.href = targetUrl;
            }
        })();
    </script>
</body>

</html>
<?php exit; ?>