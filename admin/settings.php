<?php
require_once '../db_connect.php';

// STRICT ADMIN CHECK
if(!isLoggedIn()) redirect('../login.php');
if(!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    redirect('../dashboard.php');
}

$success = '';
$error = '';

// Add Emergency Contact
if(isset($_POST['add_contact'])) {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $type = sanitize($_POST['type']);
    $region = sanitize($_POST['region']);
    
    mysqli_query($conn, "INSERT INTO emergency_contacts (name, phone, type, region) VALUES ('$name', '$phone', '$type', '$region')");
    $success = 'Emergency contact added!';
}

// Add Disaster Tip
if(isset($_POST['add_tip'])) {
    $category = sanitize($_POST['category']);
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $icon = sanitize($_POST['icon']);
    $image_url = sanitize($_POST['image_url']);
    
    mysqli_query($conn, "INSERT INTO disaster_tips (category, title, content, icon, image_url) VALUES ('$category', '$title', '$content', '$icon', '$image_url')");
    $success = 'Disaster tip added!';
}

// Add Quiz Question
if(isset($_POST['add_question'])) {
    $question = sanitize($_POST['question']);
    $a = sanitize($_POST['option_a']);
    $b = sanitize($_POST['option_b']);
    $c = sanitize($_POST['option_c']);
    $d = sanitize($_POST['option_d']);
    $correct = sanitize($_POST['correct_answer']);
    $category = sanitize($_POST['question_category']);
    $points = (int)$_POST['points'];
    
    mysqli_query($conn, "INSERT INTO quiz_questions (question, option_a, option_b, option_c, option_d, correct_answer, category, points) VALUES ('$question', '$a', '$b', '$c', '$d', '$correct', '$category', $points)");
    $success = 'Quiz question added!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel | SmartEcoTrack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --green-dark: #0A2E1F; --green-mid: #1B4D3E; --green-accent: #4CAF50; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #F1F8E9; }
        
        .navbar {
            background: linear-gradient(135deg, #1B4D3E, #2D6A4F);
            padding: 15px 30px; color: white; display: flex; justify-content: space-between;
            align-items: center;
        }
        .navbar a { color: white; text-decoration: none; margin: 0 10px; font-weight: 500; }
        .navbar .brand { font-size: 1.3rem; font-weight: 800; }
        .navbar .admin-badge { background: #FFD700; color: #333; padding: 5px 15px; border-radius: 20px; font-weight: 700; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .header {
            background: linear-gradient(135deg, #1B4D3E, #2D6A4F);
            color: white; padding: 30px; border-radius: 20px; margin-bottom: 30px;
        }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        
        .card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .card h3 { color: #1B4D3E; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .card.full { grid-column: 1 / -1; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #333; font-weight: 600; font-size: 0.9rem; }
        input, select, textarea {
            width: 100%; padding: 10px 15px; border: 2px solid #ddd; border-radius: 10px;
            font-size: 14px; font-family: inherit; transition: 0.3s;
        }
        input:focus, select:focus, textarea:focus { border-color: #4CAF50; outline: none; }
        textarea { resize: vertical; min-height: 80px; }
        
        .btn-submit {
            width: 100%; padding: 12px; background: #4CAF50; color: white; border: none;
            border-radius: 25px; font-size: 16px; font-weight: 700; cursor: pointer; transition: 0.3s;
        }
        .btn-submit:hover { background: #45a049; transform: translateY(-2px); }
        
        @media (max-width: 768px) {
            .settings-grid { grid-template-columns: 1fr; }
            .navbar { flex-direction: column; gap: 10px; text-align: center; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div>
            <a href="../dashboard.php" class="brand">🌿 SmartEcoTrack</a>
            <span class="admin-badge"><i class="fas fa-crown"></i> ADMIN PANEL</span>
        </div>
        <div>
            <a href="../dashboard.php">Dashboard</a>
            <a href="reports.php">Reports</a>
            <a href="users.php">Users</a>
            <a href="settings.php" style="color:#FFD700;">Settings</a>
            <a href="../logout.php" style="color:#ff6b6b;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-cog"></i> System Settings</h1>
            <p>Manage emergency contacts, disaster tips, and quiz questions</p>
        </div>

        <?php if($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Add Emergency Contact -->
            <div class="card">
                <h3><i class="fas fa-phone-alt"></i> Add Emergency Contact</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Organization Name</label>
                        <input type="text" name="name" placeholder="e.g., Philippine Red Cross" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" placeholder="e.g., 143" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <input type="text" name="type" placeholder="e.g., Medical Emergency" required>
                    </div>
                    <div class="form-group">
                        <label>Region</label>
                        <input type="text" name="region" placeholder="e.g., Nationwide">
                    </div>
                    <button type="submit" name="add_contact" class="btn-submit">
                        <i class="fas fa-plus"></i> Add Contact
                    </button>
                </form>
            </div>

            <!-- Add Disaster Tip -->
            <div class="card">
                <h3><i class="fas fa-lightbulb"></i> Add Disaster Tip</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" placeholder="e.g., Earthquake" required>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" placeholder="e.g., During an Earthquake" required>
                    </div>
                    <div class="form-group">
                        <label>Content</label>
                        <textarea name="content" placeholder="Write the tip content here..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Icon (Font Awesome)</label>
                        <input type="text" name="icon" placeholder="e.g., house, shield, water">
                    </div>
                    <div class="form-group">
                        <label>Image URL (Optional)</label>
                        <input type="text" name="image_url" placeholder="https://images.unsplash.com/...">
                    </div>
                    <button type="submit" name="add_tip" class="btn-submit">
                        <i class="fas fa-plus"></i> Add Tip
                    </button>
                </form>
            </div>

            <!-- Add Quiz Question -->
            <div class="card full">
                <h3><i class="fas fa-question-circle"></i> Add Quiz Question</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Question</label>
                        <textarea name="question" placeholder="Enter the question..." required></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div class="form-group">
                            <label>Option A</label>
                            <input type="text" name="option_a" required>
                        </div>
                        <div class="form-group">
                            <label>Option B</label>
                            <input type="text" name="option_b" required>
                        </div>
                        <div class="form-group">
                            <label>Option C</label>
                            <input type="text" name="option_c" required>
                        </div>
                        <div class="form-group">
                            <label>Option D</label>
                            <input type="text" name="option_d" required>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;">
                        <div class="form-group">
                            <label>Correct Answer</label>
                            <select name="correct_answer" required>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <input type="text" name="question_category" placeholder="e.g., Earthquake" required>
                        </div>
                        <div class="form-group">
                            <label>Points</label>
                            <input type="number" name="points" value="10" required>
                        </div>
                    </div>
                    <button type="submit" name="add_question" class="btn-submit">
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>