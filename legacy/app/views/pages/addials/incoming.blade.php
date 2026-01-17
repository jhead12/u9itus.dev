<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Gather action="handle-user-input.php" numDigits="1">
        <Say>Welcome to the Ad Dial System.</Say>
        <Say>We see you are a platinum member. Thank You.</Say>
        <Say>To View your credits, press 1.</Say>
        <Say>To listen to an Ad Dial, press 2.</Say>
        <Say>To check your package status, press 3.</Say>
    </Gather>
    <!-- If customer doesn't input anything, prompt and try again. -->
    <Say>Sorry, I didn't get your response.</Say>
    <Redirect>handle-incoming-call.xml</Redirect>
</Response>