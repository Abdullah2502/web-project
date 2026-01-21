<?php
session_start();
// Adjust the path if your file structure is different. 
// Assuming this file is inside the 'auth/' folder.
include '../config/db_connect.php';

$errorMsg = "";
$successMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize Inputs
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $company_name = $conn->real_escape_string($_POST['company_name']);
    $license_number = $conn->real_escape_string($_POST['license_number']);
    $website = $conn->real_escape_string($_POST['website']);

    // 2. Check for duplicate Email/Username
    $check = "SELECT * FROM users WHERE email='$email' OR username='$username'";
    $rs = $conn->query($check);

    if ($rs->num_rows > 0) {
        $errorMsg = "Username or Email already taken!";
    } else {
        // 3. Handle File Upload (Verification Document)
        $upload_dir = '../uploads/documents/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['document']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Validate file type
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($file_type, $allowed_types)) {
            $errorMsg = "Invalid file type! Only PDF, JPG, JPEG, & PNG allowed.";
        } else {
            if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
                // File upload success, proceed to DB
                
                // 4. Hash Password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 5. Insert into USERS table
                // Role is 'producer', is_active defaults to 1
                $sql_user = "INSERT INTO users (username, email, password_hash, role, is_active) 
                             VALUES ('$username', '$email', '$hashed_password', 'producer', 1)";

                if ($conn->query($sql_user) === TRUE) {
                    $new_user_id = $conn->insert_id;

                    // 6. Insert into PRODUCERS table
                    // Save relative path for the document
                    $db_doc_path = 'uploads/documents/' . $file_name;
                    
                    $sql_producer = "INSERT INTO producers (user_id, company_name, license_number, document_path, website, verification_status) 
                                     VALUES ('$new_user_id', '$company_name', '$license_number', '$db_doc_path', '$website', 'pending')";

                    if ($conn->query($sql_producer) === TRUE) {
                        // Optional: Initialize Wallet
                        $conn->query("INSERT INTO wallets (user_id, balance) VALUES ('$new_user_id', 0.00)");

                        echo "<script>alert('Registration Successful! Your account is pending verification.'); window.location.href='auth.php';</script>";
                        exit();
                    } else {
                        $errorMsg = "Error inserting producer profile: " . $conn->error;
                    }
                } else {
                    $errorMsg = "Error creating user: " . $conn->error;
                }
            } else {
                $errorMsg = "Error uploading file. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Producer Registration</title>
<link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  * { margin:0; padding:0; box-sizing:border-box; font-family: 'Lato', sans-serif; }

  body {
    background: radial-gradient(circle at top, #0d1b3d 0%, #050914 60%);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    color: white;
    padding: 20px;
  }

  .prodReg-container {
    background: rgba(10, 20, 40, 0.95);
    padding: 40px;
    border-radius: 16px;
    width: 100%;
    max-width: 650px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.6);
  }

  h2 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 25px;
    color: #ffffff;
    text-align: center;
  }

  .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 8px;
      font-weight: 600;
      text-align: center;
  }
  .alert-error {
      background: rgba(255, 77, 77, 0.2);
      border: 1px solid #ff4d4d;
      color: #ff4d4d;
  }

  .prodReg-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
  }

  .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
  }

  .input-group {
    display: flex;
    flex-direction: column;
  }

  .input-group.full-width {
    grid-column: 1 / -1;
  }

  .prodReg-form label {
    font-size: 14px;
    margin-bottom: 5px;
    opacity: 0.9;
    font-weight: 600;
    color: #a0aabf;
  }

  .prodReg-form input {
    width: 100%;
    padding: 14px;
    border-radius: 8px;
    border: 1px solid #2a5cff;
    background: #0f172a;
    color: white;
    font-size: 15px;
    outline: none;
    transition: 0.3s;
  }

  .prodReg-form input:focus {
    border-color: #4da3ff;
    background: #151e33;
    box-shadow: 0 0 0 3px rgba(42, 92, 255, 0.2);
  }

  .prodReg-btn {
    padding: 16px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 700;
    background: #2a9bff;
    color: white;
    margin-top: 10px;
    transition: 0.2s;
  }

  .prodReg-btn:hover {
    background: #4da3ff;
    transform: translateY(-2px);
  }

  .login-link {
    text-align: center;
    margin-top: 15px;
    font-size: 14px;
  }
  
  .login-link a {
    color: #4da3ff;
    text-decoration: none;
  }
  
  .login-link a:hover {
    text-decoration: underline;
  }

  @media (max-width: 600px) {
    .grid-2 { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<div class="prodReg-container">
  <h2>Producer Registration</h2>
  
  <?php if (!empty($errorMsg)): ?>
      <div class="alert alert-error"><?php echo $errorMsg; ?></div>
  <?php endif; ?>

  <form class="prodReg-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
    
    <div class="grid-2">
      <div class="input-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="CompanyHandle" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
      </div>
      <div class="input-group">
        <label>Company Name</label>
        <input type="text" name="company_name" placeholder="Studio Name" value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>" required>
      </div>
    </div>

    <div class="input-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="contact@studio.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
    </div>

    <div class="input-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Create a strong password" required>
    </div>

    <div class="grid-2">
      <div class="input-group">
        <label>License Number</label>
        <input type="text" name="license_number" placeholder="REG-123456" value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>" required>
      </div>
      <div class="input-group">
        <label>Website (Optional)</label>
        <input type="url" name="website" placeholder="https://" value="<?php echo isset($_POST['website']) ? htmlspecialchars($_POST['website']) : ''; ?>">
      </div>
    </div>

    <div class="input-group full-width">
      <label>Verification Document (License/ID)</label>
      <input type="file" name="document" accept=".pdf,.jpg,.png,.jpeg" required style="padding: 10px; background: #0b1121;">
      <small style="color: #6c7a96; margin-top: 5px;">Upload a PDF or Image of your business license.</small>
    </div>

    <button type="submit" class="prodReg-btn">Register as Producer</button>
  </form>
  
  <div class="login-link">
      Already have an account? <a href="auth.php">Login here</a>
  </div>
</div>

</body>
</html>