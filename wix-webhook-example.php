<?php
/**
 * Wix JWT Webhook Example
 * 
 * This is a standalone example showing how to verify JWT-signed webhooks from Wix.
 * For production use, see App\Http\Controllers\Wix\WebhookController
 * 
 * Usage:
 *   1. Install dependencies: composer require firebase/php-jwt
 *   2. Run: php -S localhost:3000 wix-webhook-example.php
 *   3. Configure Wix to send webhooks to http://localhost:3000
 */

require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Wix public key for webhook verification (provided by Wix)
$publicKey = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuWcRHyUpBhPhDFutLwGh
MuH0fxzcpLDxxmzYVFb1o5i9OGe8xlz7NPEFp2CJeSWsmfzwJCNsrMJavxveW+V2
kTPg9/3Ru3tFEaufjJ21CnajQoHWUrpgH8lrlhOgWdzIgx8IFfS6fGYMiZnFe/Y9
hBGMdDVwPZwByDqjTIkXoMhjMNAG1o3A9vHJm0dIOLDv2HlgapsdSN9WafHmWBcZ
EFlKd65et8RS0ZKo+UDQkBvau5w1ajk5xv5nJESCeBxe6je2jHoU3LCfAAJ0Icm6
Z4G/W7WNcbK/ZmuWTGs/4IgnFYMhAn2H9Ab++Tcf+D6pNm3O12IuZIjwC516sdXm
fwIDAQAB
-----END PUBLIC KEY-----
EOD;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    
    try {
        // Decode and verify the JWT token
        $decoded = JWT::decode($body, new Key($publicKey, 'RS256'));
        
        // Extract the nested event data (Wix nests the data in a specific format)
        $event = json_decode($decoded->data);
        $eventData = json_decode($event->data);
        
        // Log the received event
        error_log("✓ JWT verification successful");
        error_log("Event Type: " . $event->eventType);
        error_log("Instance ID: " . $event->instanceId);
        error_log("Event Data: " . json_encode($eventData, JSON_PRETTY_PRINT));
        
    } catch (Exception $e) {
        http_response_code(400);
        error_log("✗ Webhook error: " . $e->getMessage());
        exit();
    }

    // Handle specific event types
    switch ($event->eventType) {
        case "wix.headless.v1.o_auth_app_created":
            error_log("→ OAuth app created event received");
            error_log("  App instance ID: " . $event->instanceId);
            // TODO: Store app installation in database
            break;
            
        case "wix.headless.v1.o_auth_app_removed":
            error_log("→ OAuth app removed event received");
            error_log("  App instance ID: " . $event->instanceId);
            // TODO: Mark app as uninstalled in database
            break;
            
        default:
            error_log("→ Received unknown event type: " . $event->eventType);
            break;
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} else {
    // Display test page for GET requests
    http_response_code(200);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Wix JWT Webhook Endpoint</title>
        <style>
            body { font-family: system-ui, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
            code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <h1>🔐 Wix JWT Webhook Endpoint</h1>
        <p>This endpoint is ready to receive JWT-signed webhooks from Wix.</p>
        
        <h2>Configuration</h2>
        <ul>
            <li><strong>Endpoint URL:</strong> <code><?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?></code></li>
            <li><strong>Method:</strong> POST</li>
            <li><strong>Verification:</strong> JWT with RS256 signature</li>
        </ul>
        
        <h2>Supported Events</h2>
        <ul>
            <li><code>wix.headless.v1.o_auth_app_created</code> - App installed</li>
            <li><code>wix.headless.v1.o_auth_app_removed</code> - App uninstalled</li>
        </ul>
        
        <h2>Testing</h2>
        <p>To test this endpoint, configure it in your Wix app settings at <a href="https://dev.wix.com" target="_blank">dev.wix.com</a>.</p>
        <p>Watch the console/logs for incoming webhook events.</p>
    </body>
    </html>
    <?php
}
