<?php
// Start session for user authentication
session_start();

// File paths for data storage
$usersFile = 'users.json';
$passwordsFile = 'passwords.json';
$messagesFile = 'messages.json';
$uploadsDir = 'uploads/';

// Create directories if they don't exist
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}
if (!file_exists($uploadsDir . 'profiles/')) {
    mkdir($uploadsDir . 'profiles/', 0777, true);
}
if (!file_exists($uploadsDir . 'messages/')) {
    mkdir($uploadsDir . 'messages/', 0777, true);
}

// Create files if they don't exist
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode([]));
}
if (!file_exists($passwordsFile)) {
    file_put_contents($passwordsFile, json_encode([]));
}
if (!file_exists($messagesFile)) {
    file_put_contents($messagesFile, json_encode([]));
}

// Function to get all users
function getUsers() {
    global $usersFile;
    return json_decode(file_get_contents($usersFile), true) ?: [];
}

// Function to get all passwords
function getPasswords() {
    global $passwordsFile;
    return json_decode(file_get_contents($passwordsFile), true) ?: [];
}

// Function to get all messages
function getMessages() {
    global $messagesFile;
    return json_decode(file_get_contents($messagesFile), true) ?: [];
}

// Function to save users
function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
}

// Function to save passwords
function savePasswords($passwords) {
    global $passwordsFile;
    file_put_contents($passwordsFile, json_encode($passwords, JSON_PRETTY_PRINT));
}

// Function to save messages
function saveMessages($messages) {
    global $messagesFile;
    file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT));
}

// Function to generate a random avatar if no profile image is uploaded
function generateAvatar($username) {
    return "https://api.dicebear.com/7.x/avataaars/svg?seed=" . urlencode($username);
}

// Function to handle file uploads
function handleFileUpload($file, $targetDir) {
    $targetFile = $targetDir . basename($file["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $newFileName = uniqid() . '.' . $imageFileType;
    $targetFile = $targetDir . $newFileName;
    
    // Check if file is an actual image or video
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" && $imageFileType != "mp4" && $imageFileType != "mov") {
        return ["success" => false, "message" => "Sorry, only JPG, JPEG, PNG, GIF, MP4 & MOV files are allowed."];
    }
    
    // Check file size (limit to 10MB)
    if ($file["size"] > 10000000) {
        return ["success" => false, "message" => "Sorry, your file is too large. Max 10MB allowed."];
    }
    
    // Upload the file
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ["success" => true, "file" => $newFileName];
    } else {
        return ["success" => false, "message" => "Sorry, there was an error uploading your file."];
    }
}

// Initialize variables
$error = '';
$success = '';
$activeTab = 'register'; // Default to register tab

// Handle user registration
if (isset($_POST['register'])) {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    
    // Validate input
    if (strlen($username) < 3) {
        $error = "Username must be at least 3 characters";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $users = getUsers();
        $passwords = getPasswords();
        
        // Check if username already exists
        $userExists = false;
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                $userExists = true;
                break;
            }
        }
        
        if ($userExists) {
            $error = "Username already exists";
        } else {
            // Handle profile image upload
            $profileImage = "";
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $uploadResult = handleFileUpload($_FILES['profile_image'], $uploadsDir . 'profiles/');
                if ($uploadResult['success']) {
                    $profileImage = $uploadsDir . 'profiles/' . $uploadResult['file'];
                } else {
                    $error = $uploadResult['message'];
                }
            }
            
            if (empty($error)) {
                // Create new user
                $userId = uniqid();
                $users[] = [
                    'id' => $userId,
                    'fullname' => $fullname,
                    'username' => $username,
                    'email' => $email,
                    'phone' => $phone,
                    'avatar' => $profileImage ?: generateAvatar($username),
                    'lastActive' => time()
                ];
                
                $passwords[] = [
                    'userId' => $userId,
                    'username' => $username,
                    'password' => $password // In a real app, you should hash this!
                ];
                
                saveUsers($users);
                savePasswords($passwords);
                
                // Set success message and switch to login tab
                $success = "Account created successfully! Please login.";
                $activeTab = 'login';
            }
        }
    }
}

// Handle user login
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $activeTab = 'login';

    $users = getUsers();
    $passwords = getPasswords();
    $userId = null;

    // Find user by username and password
    foreach ($passwords as $userPass) {
        if ($userPass['username'] === $username && $userPass['password'] === $password) {
            $userId = $userPass['userId'];
            break;
        }
    }

    if ($userId) {
        // Update last active time
        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $user['lastActive'] = time();
                break;
            }
        }
        saveUsers($users);
        
        // Set session
        $_SESSION['userId'] = $userId;
        $_SESSION['username'] = $username;
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "Invalid username or password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle sending messages (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'sendMessage') {
    if (!isset($_SESSION['userId'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $receiverId = $_POST['receiverId'];
    $text = trim($_POST['text']);
    $messageType = 'text';
    $mediaFile = '';

    // Handle media upload
    if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $uploadResult = handleFileUpload($_FILES['media'], $uploadsDir . 'messages/');
        if ($uploadResult['success']) {
            $mediaFile = $uploadsDir . 'messages/' . $uploadResult['file'];
            $fileExt = strtolower(pathinfo($mediaFile, PATHINFO_EXTENSION));
            $messageType = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video';
        } else {
            echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
            exit;
        }
    }

    if (empty($text) && empty($mediaFile)) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit;
    }

    $messages = getMessages();

    // Create conversation ID (sorted user IDs to ensure consistency)
    $ids = [$_SESSION['userId'], $receiverId];
    sort($ids);
    $conversationId = implode('-', $ids);

    // Initialize conversation if it doesn't exist
    if (!isset($messages[$conversationId])) {
        $messages[$conversationId] = [];
    }

    // Add message
    $messages[$conversationId][] = [
        'id' => uniqid(),
        'senderId' => $_SESSION['userId'],
        'text' => $text,
        'type' => $messageType,
        'media' => $mediaFile,
        'timestamp' => time()
    ];

    saveMessages($messages);

    echo json_encode(['success' => true]);
    exit;
}

// Handle getting messages (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'getMessages') {
    if (!isset($_SESSION['userId'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $receiverId = $_GET['receiverId'];

    // Create conversation ID (sorted user IDs to ensure consistency)
    $ids = [$_SESSION['userId'], $receiverId];
    sort($ids);
    $conversationId = implode('-', $ids);

    $messages = getMessages();
    // THE CRITICAL FIX (original line had $conversationMessages):
    $conversationMessages = isset($messages[$conversationId]) ? $messages[$conversationId] : [];

    echo json_encode([
        'success' => true,
        'messages' => $conversationMessages
    ]);
    exit;
}

// Handle getting online users (AJAX)
if (isset($_GET['action']) && $_GET['action'] === 'getOnlineUsers') {
    if (!isset($_SESSION['userId'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $users = getUsers();
    $onlineUsers = [];
    $offlineUsers = [];
    $currentTime = time();

    // Consider users active in the last 5 minutes as "online"
    foreach ($users as $user) {
        if ($user['id'] !== $_SESSION['userId']) {
            if (isset($user['lastActive']) && ($currentTime - $user['lastActive']) < 300) {
                $onlineUsers[] = $user;
            } else {
                $offlineUsers[] = $user;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'onlineUsers' => $onlineUsers,
        'offlineUsers' => $offlineUsers
    ]);
    exit;
}

// Update user's last active time (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'updateActivity') {
    if (!isset($_SESSION['userId'])) {
        echo json_encode(['success' => false]);
        exit;
    }

    $users = getUsers();

    foreach ($users as &$user) {
        if ($user['id'] === $_SESSION['userId']) {
            $user['lastActive'] = time();
            break;
        }
    }

    saveUsers($users);

    echo json_encode(['success' => true]);
    exit;
}

// Get current user info
$currentUser = null;
if (isset($_SESSION['userId'])) {
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['id'] === $_SESSION['userId']) {
            $currentUser = $user;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Chat App</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
   /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-image: url('https://images.unsplash.com/photo-1557682250-33bd709cbe85?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2029&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: -1;
        }
        
        /* Auth container styles */
        .auth-container {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out, floatUp 0.8s ease-out;
            transform: translateZ(0);
            backface-visibility: hidden;
            perspective: 1000px;
        }
        
        .auth-header {
            background: linear-gradient(135deg, #8a63d2, #e23a85);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            animation: shimmer 5s infinite linear;
        }
        
        .auth-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .auth-header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .auth-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            background-color: white;
        }
        
        .auth-tab {
            flex: 1;
            text-align: center;
            padding: 18px;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .auth-tab::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, #8a63d2, #e23a85);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }
        
        .auth-tab:hover::before {
            width: 40%;
        }
        
        .auth-tab.active {
            color: #8a63d2;
        }
        
        .auth-tab.active::before {
            width: 100%;
        }
        
        .auth-form {
            padding: 30px;
            display: none;
            background-color: white;
        }
        
        .auth-form.active {
            display: block;
            animation: slideUp 0.5s ease-out;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }
        
        .form-control:focus {
            border-color: #8a63d2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(138, 99, 210, 0.2);
            background-color: white;
        }
        
        .form-group:focus-within label {
            color: #8a63d2;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-button {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px;
            background-color: #f0f0f0;
            border: 2px dashed #ccc;
            border-radius: 10px;
            color: #666;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .file-input-button:hover {
            background-color: #e8e8e8;
            border-color: #8a63d2;
            color: #8a63d2;
        }
        
        .file-input-button i {
            margin-right: 8px;
        }
        
        .file-preview {
            margin-top: 10px;
            text-align: center;
            display: none;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 150px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 8px;
            padding: 8px 12px;
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 5px;
            animation: shake 0.5s;
        }
        
        .success-message {
            color: #2ecc71;
            font-size: 14px;
            margin-top: 8px;
            margin-bottom: 15px;
            padding: 10px 15px;
            background-color: rgba(46, 204, 113, 0.1);
            border-radius: 5px;
            animation: fadeIn 0.5s;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #8a63d2, #e23a85);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(138, 99, 210, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.5s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(138, 99, 210, 0.4);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:active {
            transform: translateY(1px);
        }
        
        /* Chat container styles */
        .chat-container {
            display: flex;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 1200px;
            height: 85vh;
            max-height: 800px;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out, floatUp 0.8s ease-out;
            transform: translateZ(0);
            backface-visibility: hidden;
            perspective: 1000px;
        }
        
        .sidebar {
            width: 320px;
            background-color: #f8f9fa;
            border-right: 1px solid #eee;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .user-profile {
            padding: 20px;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #8a63d2, #e23a85);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .user-profile::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            animation: shimmer 5s infinite linear;
        }
        
        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            background-color: #ddd;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }
        
        .avatar:hover {
            transform: scale(1.05);
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s;
        }
        
        .avatar:hover img {
            transform: scale(1.1);
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-info h3 {
            font-size: 18px;
            margin-bottom: 4px;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .user-status {
            font-size: 13px;
            opacity: 0.9;
            display: flex;
            align-items: center;
        }
        
        .user-status::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #2ecc71;
            border-radius: 50%;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 20px;
            opacity: 0.8;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-btn:hover {
            opacity: 1;
            background-color: rgba(255, 255, 255, 0.2);
            transform: rotate(360deg);
        }
        
        .user-search {
            padding: 15px;
            border-bottom: 1px solid #eee;
            background-color: white;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #eee;
            border-radius: 30px;
            font-size: 14px;
            background-color: #f9f9f9;
            transition: all 0.3s;
            padding-left: 40px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23999' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 15px center;
        }
        
        .search-input:focus {
            border-color: #8a63d2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(138, 99, 210, 0.2);
            background-color: white;
        }
        
        .users-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background-color: white;
        }
        
        .users-section {
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
            padding-left: 5px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 8px;
            background-color: #f9f9f9;
            border: 1px solid transparent;
        }
        
        .user-item:hover {
            background-color: #f0f0f0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-color: #eee;
        }
        
        .user-item.active {
            background-color: rgba(138, 99, 210, 0.1);
            border-color: rgba(138, 99, 210, 0.3);
        }
        
        .user-item .avatar {
            position: relative;
            width: 40px;
            height: 40px;
            margin-right: 12px;
        }
        
        .user-item .status-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .status-online {
            background-color: #2ecc71;
        }
        
        .status-offline {
            background-color: #95a5a6;
        }
        
        .user-item .user-name {
            font-size: 15px;
            font-weight: 500;
            color: #333;
            transition: all 0.3s;
        }
        
        .user-item:hover .user-name {
            color: #8a63d2;
        }
        
        .chat-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: white;
        }
        
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            background-color: #f9f9f9;
        }
        
        .chat-header .avatar {
            width: 45px;
            height: 45px;
        }
        
        .chat-header .user-info {
            margin-left: 15px;
        }
        
        .chat-header h3 {
            font-size: 18px;
            margin-bottom: 4px;
            color: #333;
        }
        
        .chat-header .user-status {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
        }
        
        .chat-header .user-status::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .chat-header .user-status.online::before {
            background-color: #2ecc71;
            animation: pulse 2s infinite;
        }
        
        .chat-header .user-status.offline::before {
            background-color: #95a5a6;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f9f9f9;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1IiBoZWlnaHQ9IjUiPgo8cmVjdCB3aWR0aD0iNSIgaGVpZ2h0PSI1IiBmaWxsPSIjZmZmIj48L3JlY3Q+CjxyZWN0IHdpZHRoPSIxIiBoZWlnaHQ9IjEiIGZpbGw9IiNmMGYwZjAiPjwvcmVjdD4KPC9zdmc+');
        }
        
        .message {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-end;
            animation: messageIn 0.5s ease-out;
            max-width: 80%;
        }
        
        .message.outgoing {
            flex-direction: row-reverse;
            margin-left: auto;
        }
        
        .message .avatar {
            width: 35px;
            height: 35px;
            margin: 0 10px;
        }
        
        .message.outgoing .avatar {
            display: none;
        }
        
        .message-content {
            max-width: 100%;
        }
        
        .message-bubble {
            padding: 12px 18px;
            border-radius: 18px;
            font-size: 15px;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            word-break: break-word;
        }
        
        .message.incoming .message-bubble {
            background-color: white;
            border: 1px solid #eee;
            border-bottom-left-radius: 5px;
        }
        
        .message.outgoing .message-bubble {
            background: linear-gradient(135deg, #8a63d2, #e23a85);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message-media {
            margin-top: 5px;
            max-width: 300px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .message-media img {
            width: 100%;
            display: block;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .message-media img:hover {
            transform: scale(1.02);
        }
        
        .message-media video {
            width: 100%;
            display: block;
            border-radius: 10px;
        }
        
        .message-time {
            font-size: 12px;
            margin-top: 5px;
            opacity: 0.7;
        }
        
        .message.incoming .message-time {
            text-align: left;
            color: #666;
        }
        
        .message.outgoing .message-time {
            text-align: right;
            color: #666;
        }
        
        .chat-input {
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
            background-color: white;
        }
        
        .message-input-container {
            flex: 1;
            position: relative;
        }
        
        .message-input {
            width: 100%;
            padding: 14px 50px 14px 20px;
            border: 2px solid #eee;
            border-radius: 30px;
            font-size: 15px;
            background-color: #f9f9f9;
            transition: all 0.3s;
        }
        
        .message-input:focus {
            border-color: #8a63d2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(138, 99, 210, 0.2);
            background-color: white;
        }
        
        .media-upload-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #8a63d2;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .media-upload-btn:hover {
            background-color: rgba(138, 99, 210, 0.1);
        }
        
        .media-upload-btn input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .send-btn {
            background: linear-gradient(135deg, #8a63d2, #e23a85);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-left: 15px;
            box-shadow: 0 3px 10px rgba(138, 99, 210, 0.3);
        }
        
        .send-btn:hover {
            transform: scale(1.05) rotate(10deg);
            box-shadow: 0 5px 15px rgba(138, 99, 210, 0.4);
        }
        
        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #999;
            text-align: center;
            padding: 30px;
            animation: fadeIn 1s;
        }
        
        .empty-state i {
            font-size: 70px;
            margin-bottom: 20px;
            opacity: 0.3;
            color: #8a63d2;
            animation: float 3s infinite ease-in-out;
        }
        
        .empty-state h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: #555;
        }
        
        .empty-state p {
            font-size: 16px;
            max-width: 300px;
            line-height: 1.5;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 992px) {
            .chat-container {
                height: 90vh;
                max-height: none;
            }
            
            .sidebar {
                width: 280px;
            }
        }
        
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                height: 95vh;
            }
            
            .sidebar {
                width: 100%;
                height: 60px;
                flex-direction: row;
                overflow: hidden;
                transition: height 0.3s;
            }
            
            .sidebar.expanded {
                height: 350px;
            }
            
            .user-profile {
                width: 100%;
                padding: 10px 15px;
            }
            
            .mobile-toggle {
                display: block;
                position: absolute;
                top: 15px;
                right: 15px;
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                z-index: 10;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s;
            }
            
            .mobile-toggle:hover {
                background-color: rgba(255, 255, 255, 0.2);
            }
            
            .users-list {
                display: none;
            }
            
            .sidebar.expanded .users-list {
                display: block;
            }
            
            .chat-content {
                height: calc(95vh - 60px);
            }
            
            .message {
                max-width: 90%;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes floatUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(15px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes messageIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0); }
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(138, 99, 210, 0.3);
            border-radius: 50%;
            border-top-color: #8a63d2;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Typing indicator */
        .typing-indicator {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            animation: fadeIn 0.5s;
        }
        
        .typing-indicator .avatar {
            width: 35px;
            height: 35px;
            margin-right: 10px;
        }
        
        .typing-bubble {
            background-color: rgba(240, 240, 240, 0.8);
            padding: 12px 18px;
            border-radius: 18px;
            border-bottom-left-radius: 5px;
            display: flex;
            align-items: center;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            background-color: #999;
            border-radius: 50%;
            margin: 0 3px;
            animation: typingBounce 1.3s infinite;
        }
        
        .typing-dot:nth-child(2) {
            animation-delay: 0.15s;
        }
        
        .typing-dot:nth-child(3) {
            animation-delay: 0.3s;
        }
        
        @keyframes typingBounce {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-8px); }
        }
        
        /* Media viewer */
        .media-viewer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .media-viewer.active {
            opacity: 1;
            visibility: visible;
        }
        
        .media-viewer img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 5px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3);
        }
        
        .media-viewer-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .media-viewer-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }
        
        /* Style 1: Neon Theme */
        .style-1 .auth-header,
        .style-1 .user-profile {
            background: linear-gradient(135deg, #ff00cc, #3333ff);
        }
        
        .style-1 .btn,
        .style-1 .send-btn {
            background: linear-gradient(135deg, #ff00cc, #3333ff);
        }
        
        .style-1 .message.outgoing .message-bubble {
            background: linear-gradient(135deg, #ff00cc, #3333ff);
        }
        
        .style-1 .auth-tab.active,
        .style-1 .form-group:focus-within label {
            color: #ff00cc;
        }
        
        .style-1 .form-control:focus,
        .style-1 .message-input:focus {
            border-color: #ff00cc;
            box-shadow: 0 0 0 3px rgba(255, 0, 204, 0.2);
        }
        
        /* Style 2: Ocean Theme */
        .style-2 .auth-header,
        .style-2 .user-profile {
            background: linear-gradient(135deg, #00c6ff, #0072ff);
        }
        
        .style-2 .btn,
        .style-2 .send-btn {
            background: linear-gradient(135deg, #00c6ff, #0072ff);
        }
        
        .style-2 .message.outgoing .message-bubble {
            background: linear-gradient(135deg, #00c6ff, #0072ff);
        }
        
        .style-2 .auth-tab.active,
        .style-2 .form-group:focus-within label {
            color: #0072ff;
        }
        
        .style-2 .form-control:focus,
        .style-2 .message-input:focus {
            border-color: #0072ff;
            box-shadow: 0 0 0 3px rgba(0, 114, 255, 0.2);
        }
        
        /* Style 3: Sunset Theme */
        .style-3 .auth-header,
        .style-3 .user-profile {
            background: linear-gradient(135deg, #ff7e5f, #feb47b);
        }
        
        .style-3 .btn,
        .style-3 .send-btn {
            background: linear-gradient(135deg, #ff7e5f, #feb47b);
        }
        
        .style-3 .message.outgoing .message-bubble {
            background: linear-gradient(135deg, #ff7e5f, #feb47b);
        }
        
        .style-3 .auth-tab.active,
        .style-3 .form-group:focus-within label {
            color: #ff7e5f;
        }
        
        .style-3 .form-control:focus,
        .style-3 .message-input:focus {
            border-color: #ff7e5f;
            box-shadow: 0 0 0 3px rgba(255, 126, 95, 0.2);
        }
        
        /* Style 4: Forest Theme */
        .style-4 .auth-header,
        .style-4 .user-profile {
            background: linear-gradient(135deg, #56ab2f, #a8e063);
        }
        
        .style-4 .btn,
        .style-4 .send-btn {
            background: linear-gradient(135deg, #56ab2f, #a8e063);
        }
        
        .style-4 .message.outgoing .message-bubble {
            background: linear-gradient(135deg, #56ab2f, #a8e063);
        }
        
        .style-4 .auth-tab.active,
        .style-4 .form-group:focus-within label {
            color: #56ab2f;
        }
        
        .style-4 .form-control:focus,
        .style-4 .message-input:focus {
            border-color: #56ab2f;
            box-shadow: 0 0 0 3px rgba(86, 171, 47, 0.2);
        }
        
        /* Style 5: Midnight Theme */
        .style-5 .auth-header,
        .style-5 .user-profile {
            background: linear-gradient(135deg, #232526, #414345);
        }
        
        .style-5 .btn,
        .style-5 .send-btn {
            background: linear-gradient(135deg, #232526, #414345);
        }
        
        .style-5 .message.outgoing .message-bubble {
            background: linear-gradient(135deg, #232526, #414345);
        }
        
        .style-5 .auth-tab.active,
        .style-5 .form-group:focus-within label {
            color: #414345;
        }
        
        .style-5 .form-control:focus,
        .style-5 .message-input:focus {
            border-color: #414345;
            box-shadow: 0 0 0 3px rgba(65, 67, 69, 0.2);
        }
    </style>
</head>
<body>
<?php if (!$currentUser): ?>
<!-- Authentication Container -->
<div class="auth-container" id="auth-container">
    <div class="auth-header">
        <h1>Chat App</h1>
        <p>Connect with friends in real-time</p>
    </div>
    
    <div class="auth-tabs">
        <div class="auth-tab <?php echo $activeTab === 'login' ? 'active' : ''; ?>" data-tab="login">Login</div>
        <div class="auth-tab <?php echo $activeTab === 'register' ? 'active' : ''; ?>" data-tab="register">Register</div>
    </div>
    
    <div class="auth-form <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="login-form">
        <?php if (!empty($success)): ?>
        <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="login-username">Username</label>
                <input type="text" id="login-username" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" class="form-control" required>
            </div>
            <?php if (isset($error) && isset($_POST['login'])): ?>
            <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <button type="submit" name="login" class="btn">Login</button>
        </form>
    </div>
    
    <div class="auth-form <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="register-form">
        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="register-fullname">Full Name</label>
                <input type="text" id="register-fullname" name="fullname" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="register-username">Username</label>
                <input type="text" id="register-username" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="register-email">Email</label>
                <input type="email" id="register-email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="register-phone">Phone Number</label>
                <input type="tel" id="register-phone" name="phone" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="register-profile-image">Profile Image</label>
                <div class="file-input-wrapper">
                    <div class="file-input-button">
                        <i class="fas fa-camera"></i> Choose Profile Image
                    </div>
                    <input type="file" id="register-profile-image" name="profile_image" accept="image/*">
                </div>
                <div class="file-preview" id="profile-preview"></div>
            </div>
            <div class="form-group">
                <label for="register-password">Password</label>
                <input type="password" id="register-password" name="password" class="form-control" required>
            </div>
            <?php if (isset($error) && isset($_POST['register'])): ?>
            <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <button type="submit" name="register" class="btn">Create Account</button>
        </form>
    </div>
</div>
<?php else: ?>
<!-- Chat Container -->
<div class="chat-container" id="chat-container">
    <button class="mobile-toggle" id="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar" id="sidebar">
        <div class="user-profile">
            <div class="avatar">
                <img src="<?php echo $currentUser['avatar']; ?>" alt="<?php echo $currentUser['username']; ?>">
            </div>
            <div class="user-info">
                <h3><?php echo $currentUser['username']; ?></h3>
                <div class="user-status">Online</div>
            </div>
            <a href="?logout=1" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        
        <div class="user-search">
            <input type="text" class="search-input" placeholder="Search users..." id="search-input">
        </div>
        
        <div class="users-list" id="users-list">
            <div class="users-section">
                <div class="section-title">ONLINE USERS</div>
                <div id="online-users">
                    <!-- Online users will be loaded here -->
                </div>
            </div>
            
            <div class="users-section">
                <div class="section-title">OFFLINE USERS</div>
                <div id="offline-users">
                    <!-- Offline users will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <div class="chat-content">
        <div class="chat-header" id="chat-header">
            <!-- Selected user info will be displayed here -->
        </div>
        
        <div class="chat-messages" id="chat-messages">
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>Select a user to start chatting</h3>
                <p>Choose from the list of online users to begin a conversation</p>
            </div>
        </div>
        
        <div class="chat-input" id="chat-input" style="display: none;">
            <div class="message-input-container">
                <input type="text" class="message-input" id="message-input" placeholder="Type a message...">
                <button class="media-upload-btn" id="media-upload-btn">
                    <i class="fas fa-paperclip"></i>
                    <input type="file" id="media-upload" accept="image/*,video/*">
                </button>
            </div>
            <button class="send-btn" id="send-btn" disabled>
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<!-- Media Viewer -->
<div class="media-viewer" id="media-viewer">
    <div class="media-viewer-content" id="media-viewer-content"></div>
    <div class="media-viewer-close" id="media-viewer-close">
        <i class="fas fa-times"></i>
    </div>
</div>
<?php endif; ?>

<script>
    // DOM Elements
    const authContainer = document.getElementById('auth-container');
    const chatContainer = document.getElementById('chat-container');
    const authTabs = document.querySelectorAll('.auth-tab');
    const authForms = document.querySelectorAll('.auth-form');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const searchInput = document.getElementById('search-input');
    const onlineUsersContainer = document.getElementById('online-users');
    const offlineUsersContainer = document.getElementById('offline-users');
    const chatHeader = document.getElementById('chat-header');
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const messageInput = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const mediaUpload = document.getElementById('media-upload');
    const mediaViewer = document.getElementById('media-viewer');
    const mediaViewerContent = document.getElementById('media-viewer-content');
    const mediaViewerClose = document.getElementById('media-viewer-close');
    const profileImageInput = document.getElementById('register-profile-image');
    const profilePreview = document.getElementById('profile-preview');
    
    // Variables
    let selectedUser = null;
    let users = [];
    let messages = [];
    let lastMessageTimestamp = 0;
    let selectedMedia = null;
    let currentStyle = 1;
    
    // Apply random style on page load
    function applyRandomStyle() {
        const styles = 5;
        currentStyle = Math.floor(Math.random() * styles) + 1;
        
        if (authContainer) {
            authContainer.className = `auth-container style-${currentStyle}`;
        }
        
        if (chatContainer) {
            chatContainer.className = `chat-container style-${currentStyle}`;
        }
    }
    
    // Call on page load
    applyRandomStyle();
    
    // Profile image preview
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePreview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview">`;
                    profilePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Auth tabs functionality
    if (authTabs) {
        authTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and forms
                authTabs.forEach(t => t.classList.remove('active'));
                authForms.forEach(f => f.classList.remove('active'));
                
                // Add active class to clicked tab
                tab.classList.add('active');
                
                // Show corresponding form
                const formId = tab.getAttribute('data-tab') + '-form';
                document.getElementById(formId).classList.add('active');
            });
        });
    }
    
    // Mobile sidebar toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('expanded');
        });
    }
    
    // Media viewer functionality
    if (mediaViewerClose) {
        mediaViewerClose.addEventListener('click', () => {
            mediaViewer.classList.remove('active');
        });
    }
    
    // Only run chat functionality if user is logged in
    if (chatMessages) {
        // Function to format timestamp
        function formatTime(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        
        // Function to create user item
        function createUserItem(user, isOnline) {
            const userItem = document.createElement('div');
            userItem.className = 'user-item';
            userItem.dataset.userId = user.id;
            
            userItem.innerHTML = `
                <div class="avatar">
                    <img src="${user.avatar}" alt="${user.username}">
                    <span class="status-indicator ${isOnline ? 'status-online' : 'status-offline'}"></span>
                </div>
                <div class="user-name">${user.username}</div>
            `;
            
            userItem.addEventListener('click', () => {
                // Remove active class from all user items
                document.querySelectorAll('.user-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Add active class to clicked user item
                userItem.classList.add('active');
                
                // Set selected user
                selectedUser = user;
                
                // Update chat header
                updateChatHeader();
                
                // Show chat input
                chatInput.style.display = 'flex';
                
                // Load messages
                loadMessages();
                
                // On mobile, collapse sidebar after selecting a user
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('expanded');
                }
            });
            
            return userItem;
        }
        
        // Function to update chat header
        function updateChatHeader() {
            if (!selectedUser) {
                chatHeader.innerHTML = '';
                return;
            }
            
            chatHeader.innerHTML = `
                <div class="avatar">
                    <img src="${selectedUser.avatar}" alt="${selectedUser.username}">
                </div>
                <div class="user-info">
                    <h3>${selectedUser.username}</h3>
                    <div class="user-status ${selectedUser.online ? 'online' : 'offline'}">${selectedUser.online ? 'Online' : 'Offline'}</div>
                </div>
            `;
        }
        
        // Function to create message element
        function createMessageElement(message) {
            const isOutgoing = message.senderId === '<?php echo $_SESSION['userId']; ?>';
            const messageEl = document.createElement('div');
            messageEl.className = `message ${isOutgoing ? 'outgoing' : 'incoming'}`;
            
            // Find sender in users array
            const sender = users.find(u => u.id === message.senderId) || { 
                username: isOutgoing ? '<?php echo $_SESSION['username']; ?>' : selectedUser.username,
                avatar: isOutgoing ? '<?php echo $currentUser['avatar']; ?>' : selectedUser.avatar
            };
            
            let mediaHtml = '';
            if (message.type === 'image') {
                mediaHtml = `
                    <div class="message-media">
                        <img src="${message.media}" alt="Image" class="media-image" data-src="${message.media}">
                    </div>
                `;
            } else if (message.type === 'video') {
                mediaHtml = `
                    <div class="message-media">
                        <video controls>
                            <source src="${message.media}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                `;
            }
            
            messageEl.innerHTML = `
                <div class="avatar">
                    <img src="${sender.avatar}" alt="${sender.username}">
                </div>
                <div class="message-content">
                    <div class="message-bubble">${message.text}</div>
                    ${mediaHtml}
                    <div class="message-time">${formatTime(message.timestamp)}</div>
                </div>
            `;
            
            // Add click event for media images
            const mediaImage = messageEl.querySelector('.media-image');
            if (mediaImage) {
                mediaImage.addEventListener('click', () => {
                    mediaViewerContent.innerHTML = `<img src="${mediaImage.dataset.src}" alt="Full Image">`;
                    mediaViewer.classList.add('active');
                });
            }
            
            return messageEl;
        }
        
        // Function to load users
        function loadUsers() {
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=getOnlineUsers`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        users = [...data.onlineUsers, ...data.offlineUsers];
                        
                        // Clear containers
                        onlineUsersContainer.innerHTML = data.onlineUsers.length === 0 ? 
                            '<div class="section-empty">No users online</div>' : '';
                        offlineUsersContainer.innerHTML = data.offlineUsers.length === 0 ? 
                            '<div class="section-empty">No offline users</div>' : '';

                        // Add online users
                        data.onlineUsers.forEach(user => {
                            user.online = true;
                            onlineUsersContainer.appendChild(createUserItem(user, true));
                        });
                        
                        // Add offline users
                        if (data.offlineUsers.length === 0) {
                            offlineUsersContainer.innerHTML = '<div class="section-empty">No offline users</div>';
                        } else {
                            data.offlineUsers.forEach(user => {
                                user.online = false;
                                offlineUsersContainer.appendChild(createUserItem(user, false));
                            });
                        }
                        
                        // If a user was selected, update their online status
                        if (selectedUser) {
                            const updatedUser = users.find(u => u.id === selectedUser.id);
                            if (updatedUser) {
                                selectedUser = updatedUser;
                                updateChatHeader();
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                });
        }
        
        // Function to load messages
        function loadMessages() {
            if (!selectedUser) return;
            
            // Show loading state
            chatMessages.innerHTML = '<div class="loading-spinner"></div>';
            
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=getMessages&receiverId=${selectedUser.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messages = data.messages;
                        
                        if (messages.length === 0) {
                            chatMessages.innerHTML = `
                                <div class="empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <h3>No messages yet</h3>
                                    <p>Send a message to start the conversation with ${selectedUser.username}</p>
                                </div>
                            `;
                        } else {
                            chatMessages.innerHTML = '';
                            
                            // Add messages
                            messages.forEach(message => {
                                chatMessages.appendChild(createMessageElement(message));
                            });
                            
                            // Scroll to bottom
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                            
                            // Update last message timestamp
                            if (messages.length > 0) {
                                lastMessageTimestamp = Math.max(...messages.map(m => m.timestamp));
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                    chatMessages.innerHTML = '<div class="error-message">Failed to load messages</div>';
                });
        }
        
        // Function to send message
        function sendMessage() {
            if (!selectedUser) return;
            
            const text = messageInput.value.trim();
            if (!text && !selectedMedia) return;
            
            // Disable send button
            sendBtn.disabled = true;
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'sendMessage');
            formData.append('receiverId', selectedUser.id);
            formData.append('text', text);
            
            // Add media if selected
            if (selectedMedia) {
                formData.append('media', selectedMedia);
            }
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear input and selected media
                        messageInput.value = '';
                        selectedMedia = null;
                        mediaUpload.value = '';
                        
                        // Reload messages
                        loadMessages();
                    } else {
                        alert('Failed to send message: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    alert('Failed to send message');
                })
                .finally(() => {
                    // Enable send button
                    sendBtn.disabled = false;
                });
        }
        
        // Function to update user activity
        function updateActivity() {
            const formData = new FormData();
            formData.append('action', 'updateActivity');
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .catch(error => {
                    console.error('Error updating activity:', error);
                });
        }
        
        // Function to check for new messages
        function checkNewMessages() {
            if (!selectedUser) return;
            
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=getMessages&receiverId=${selectedUser.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const newMessages = data.messages.filter(m => m.timestamp > lastMessageTimestamp);
                        
                        if (newMessages.length > 0) {
                            // Add new messages
                            newMessages.forEach(message => {
                                chatMessages.appendChild(createMessageElement(message));
                            });
                            
                            // Scroll to bottom
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                            
                            // Update last message timestamp
                            lastMessageTimestamp = Math.max(...data.messages.map(m => m.timestamp));
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking new messages:', error);
                });
        }
        
        // Handle media upload
        if (mediaUpload) {
            mediaUpload.addEventListener('change', function() {
                selectedMedia = this.files[0];
                if (selectedMedia) {
                    // Enable send button if media is selected
                    sendBtn.disabled = false;
                    
                    // Show a notification that media is ready to send
                    messageInput.placeholder = `${selectedMedia.name} selected. Type a message or send now.`;
                }
            });
        }
        
        // Search functionality
        searchInput.addEventListener('input', () => {
            const searchTerm = searchInput.value.toLowerCase();
            
            document.querySelectorAll('.user-item').forEach(item => {
                const username = item.querySelector('.user-name').textContent.toLowerCase();
                
                if (username.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Send message on button click
        sendBtn.addEventListener('click', sendMessage);
        
        // Send message on Enter key
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // Enable/disable send button based on input
        messageInput.addEventListener('input', () => {
            sendBtn.disabled = messageInput.value.trim() === '' && !selectedMedia;
        });
        
        // Initial load
        loadUsers();
        
        // Update activity every 30 seconds
        updateActivity();
        setInterval(updateActivity, 30000);
        
        // Check for new messages every 5 seconds
        setInterval(checkNewMessages, 5000);
        
        // Check for new users every 30 seconds
        setInterval(loadUsers, 30000);
        
        // Change style every 60 seconds for a dynamic experience
        setInterval(() => {
            currentStyle = currentStyle % 5 + 1;
            chatContainer.className = `chat-container style-${currentStyle}`;
        }, 60000);
    }
</script>
</body>
</html>

