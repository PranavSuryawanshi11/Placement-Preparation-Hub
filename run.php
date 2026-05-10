<?php
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$code     = $data['code'] ?? '';
$language = $data['language'] ?? '';
$input    = $data['input'] ?? '';

if (empty($code) || empty($language)) {
    echo json_encode(["error" => "Missing code or language"]);
    exit;
}

// Judge0 endpoint
$submit_url = "https://ce.judge0.com/submissions?base64_encoded=false";

$post_data = json_encode([
    "source_code" => $code,
    "language_id" => intval($language),
    "stdin"       => $input,
    "wall_time_limit" => 10,     // Increased slightly for safety
    "memory_limit"    => 256000  // 256 MB
]);

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $submit_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post_data,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(["error" => "Connection error: " . curl_error($ch)]);
    curl_close($ch);
    exit;
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 201 && $http_code !== 200) {
    echo json_encode([
        "error" => "Failed to submit code to Judge0 (HTTP $http_code)",
        "details" => $response
    ]);
    exit;
}

$result = json_decode($response, true);

if (!isset($result['token']) || empty($result['token'])) {
    echo json_encode([
        "error" => "Failed to submit code",
        "details" => $result['error'] ?? $response
    ]);
    exit;
}

$token = $result['token'];

// Wait a bit longer for compilation + execution
sleep(4);

$get_url = "https://ce.judge0.com/submissions/$token?base64_encoded=false";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $get_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(["error" => "Failed to fetch result: " . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

$result = json_decode($response, true);

// Improved error handling with clear messages from Judge0 API
if (isset($result['status']['description'])) {
    $status_desc = $result['status']['description'];

    if ($status_desc === "Accepted" || $status_desc === "Runtime Error" || $status_desc === "Compilation Error") {
        if (!empty($result['stdout'])) {
            echo json_encode(["stdout" => $result['stdout']]);
        } elseif (!empty($result['stderr'])) {
            echo json_encode(["stderr" => $result['stderr']]);
        } elseif (!empty($result['compile_output'])) {
            echo json_encode(["compile_output" => $result['compile_output']]);
        } else {
            echo json_encode(["error" => $status_desc]);
        }
    } else {
        // Other statuses like Time Limit Exceeded, Memory Limit Exceeded, etc.
        $error_msg = $status_desc;
        
        if (!empty($result['message'])) {
            $error_msg .= " - " . $result['message'];
        }
        if (!empty($result['stderr'])) {
            $error_msg .= "\n\n" . $result['stderr'];
        }
        if (!empty($result['compile_output'])) {
            $error_msg .= "\n\nCompilation Output:\n" . $result['compile_output'];
        }

        echo json_encode(["error" => $error_msg]);
    }
} 
elseif (isset($result['stdout']) && $result['stdout'] !== null) {
    echo json_encode(["stdout" => $result['stdout']]);
} 
elseif (isset($result['stderr']) && $result['stderr'] !== null) {
    echo json_encode(["stderr" => $result['stderr']]);
} 
elseif (isset($result['compile_output']) && $result['compile_output'] !== null) {
    echo json_encode(["compile_output" => $result['compile_output']]);
} 
else {
    echo json_encode([
        "error" => "Execution failed or Error in Code",
        "details" => $result
    ]);
}
?>