<?php

require_once 'auth_middleware.php';



function getUserData($user_id, $role)
{

    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");

    $stmt->execute([$user_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);

}



function getAdditionalData($user_id, $role)
{

    global $pdo;

    $table = ($role === 'pembeli') ? 'customers' : 'petani';

    $stmt = $pdo->prepare("SELECT * FROM $table WHERE user_id = ?");

    $stmt->execute([$user_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);

}

function updateUserProfile($user_id, $role, $post_data)
{
    global $pdo;
    $username = $post_data['username'];
    $email = $post_data['email'];
    $no_hp = $post_data['no_hp'];
    $new_password = $post_data['new_password'];
    $confirm_password = $post_data['confirm_password'];

    $errors = [];
    $requires_reauth = false;
    $password_updated = false;
    $success_message = 'Your profile has been successfully updated.';

    // Check for duplicate username
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Username sudah digunakan";
    }

    // Check for duplicate email
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email sudah digunakan";
    }

    // Check for duplicate phone number
    $table = ($role === 'pembeli') ? 'customers' : 'petani';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE no_hp = ? AND user_id != ?");
    $stmt->execute([$no_hp, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Nomor Telepon sudah digunakan";
    }

    // Validate password
    if (!empty($new_password) || !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
    }

    if (empty($errors)) {
        // Update user data
        $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $old_email = $stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
        $stmt->execute([$username, $email, $user_id]);

        // Check if email was updated
        if ($email !== $old_email) {
            $requires_reauth = true;
        }

        // Update role-specific data
        $table = ($role === 'pembeli') ? 'customers' : 'petani';
        $stmt = $pdo->prepare("UPDATE $table SET no_hp = ? WHERE user_id = ?");
        $stmt->execute([$no_hp, $user_id]);

        // Update password if provided and validated
        if (!empty($new_password) && $new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $requires_reauth = true;
            $password_updated = true;
        }

        if ($requires_reauth) {
            $success_message = $password_updated
                ? 'Password berhasil di-update, silakan login ulang.'
                : 'Profile and berhasil diperbarui, silakan login ulang.';

            // Update the auth token to force re-authentication
            $new_token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET auth_token = ? WHERE user_id = ?");
            $stmt->execute([$new_token, $user_id]);

            // Clear the auth token cookie
            setcookie('auth_token', '', time() - 3600, '/', '', true, true);
        }

        return [true, $success_message, $requires_reauth, $password_updated];
    }

    return [false, $errors, false, false];
}