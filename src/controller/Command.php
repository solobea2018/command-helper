<?php


namespace Solobea\CommandHelper\controller;


use Exception;
use Solobea\CommandHelper\utils\Config;

class Command
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        // API access token expected from the request header
        $accessToken = Config::$APP['access_token'];

        $headers = function_exists('getallheaders')
            ? getallheaders()
            : [];

        $requestToken = $headers['Authorization'] ?? '';

        // Support: Authorization: Bearer TOKEN
        if (stripos($requestToken, 'Bearer ') === 0) {
            $requestToken = trim(substr($requestToken, 7));
        }

        // Check access token
        if (!$requestToken || !hash_equals($accessToken, $requestToken)) {
            http_response_code(401);

            echo json_encode([
                'status' => false,
                'message' => 'Unauthorized'
            ]);

            return;
        }

        // Read JSON request body
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!is_array($data)) {
            $data = [];
        }

        // Get message from JSON, POST or REQUEST
        $message = trim(
            $data['message']
            ?? $_POST['message']
            ?? $_REQUEST['message']
            ?? ''
        );

        if ($message === '') {
            http_response_code(400);

            echo json_encode([
                'status' => false,
                'message' => 'Message is required',
                'response' => null
            ]);

            return;
        }

        try {
            $response = $this->askAI($message);

            echo json_encode([
                'status' => true,
                'message' => 'Success',
                'response' => $response
            ], JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            http_response_code(500);

            echo json_encode([
                'status' => false,
                'message' => $e->getMessage(),
                'response' => null
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    private function askAI(string $userMessage): string
    {
        $apiKey = Config::$APP['api_key'];

        $url = 'https://api.openai.com/v1/chat/completions';

        $systemPrompt = <<<PROMPT
You are a helpful assistant for a system administrator.

The administrator works with CMD, Windows Terminal, PowerShell, Batch, Linux Shell, PHP, Java, CSS, HTML, networking, Cisco, MikroTik, pfSense, servers, databases, and other system administration technologies.

Understand the user's request and provide the exact command or commands needed.

Rules:
- Provide the command first.
- Add a very short comment explaining what the command does.
- Keep explanations simple and brief.
- Normally provide one command.
- If there are multiple valid approaches, provide at most 3 commands.
- If providing multiple commands, briefly label each one.
- Commands must be complete and ready to run.
- Use the correct syntax for the requested operating system, shell, language, or device.
- Do not use Markdown.
- Do not use code blocks.
- Do not use backticks.
- Do not provide long explanations.
- Do not add unnecessary information.
- Never invent command options.
- If the user's request is unclear, ask a short clarification question instead of guessing.
PROMPT;

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $userMessage
            ]
        ];

        $requestData = [
            'model' => 'gpt-6-luna',
            'messages' => $messages,
            'reasoning_effort' => 'none',
            'max_completion_tokens' => 500
        ];

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $requestData,
                JSON_UNESCAPED_UNICODE
            ),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);

        // Handle cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new Exception('cURL error: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // Decode OpenAI response
        $responseData = json_decode($response, true);

        if (!is_array($responseData)) {
            throw new Exception('Invalid API response');
        }

        // Handle OpenAI API errors
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = $responseData['error']['message']
                ?? 'OpenAI API request failed';

            throw new Exception($errorMessage);
        }

        // Extract AI response
        $content = $responseData['choices'][0]['message']['content']
            ?? null;

        if (!$content) {
            throw new Exception('No response received from AI');
        }

        return trim($content);
    }
}