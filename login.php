<?php
// login.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect_by_role($_SESSION['user_role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = trim($_POST['login_type'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_type) || empty($email) || empty($password)) {
        $error = 'Please select your role/institution and enter your credentials.';
    } else {
        if ($login_type === 'system_admin') {
            // Super Admin or Admin
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND role IN ('super_admin', 'admin') AND status = 'active' LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();
        } else {
            // College User
            $college_id = (int)$login_type;
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND college_id = :college_id AND role = 'college_user' AND status = 'active' LIMIT 1");
            $stmt->execute([':email' => $email, ':college_id' => $college_id]);
            $user = $stmt->fetch();
        }

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['college_id'] = $user['college_id'];
            $_SESSION['department_id'] = $user['department_id'];

            // Fetch and set college name if college_id exists
            if (!empty($user['college_id'])) {
                $collStmt = $pdo->prepare("SELECT name FROM colleges WHERE id = ?");
                $collStmt->execute([$user['college_id']]);
                $_SESSION['college_name'] = $collStmt->fetchColumn();
            } else {
                $_SESSION['college_name'] = null;
            }

            // Log activity
            log_activity($pdo, 'User logged in: ' . $user['email']);

            // Redirect based on role
            redirect_by_role($user['role']);
        } else {
            $error = 'Invalid email, password, or institution selection.';
        }
    }
}

// Fetch active colleges
$colleges = $pdo->query("SELECT id, name, code FROM colleges WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$college_count = count($colleges);
$sub_count = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &bull; Sri Venkateshwaraa Document & Work Tracking System</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS with Cache Buster -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body class="campus-login-body">
    <!-- Inline Custom Design Override Styles -->
    <style>
        .split-left-panel {
            flex: 1.1;
            background: linear-gradient(135deg, rgba(12, 77, 162, 0.92) 0%, rgba(5, 33, 71, 0.96) 100%), 
                        url('https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1200&q=80') center center / cover no-repeat;
            padding: 3.5rem 3rem;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .split-right-panel {
            flex: 1;
            padding: 4rem 3.5rem 4rem 5.5rem;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-top-left-radius: 60px;
            border-bottom-left-radius: 60px;
            box-shadow: -15px 0 45px rgba(0, 0, 0, 0.08);
            z-index: 10;
            margin-left: -50px;
            position: relative;
        }
        @media (max-width: 991px) {
            .split-right-panel {
                border-top-left-radius: 0;
                border-bottom-left-radius: 0;
                padding: 3rem 2rem;
                margin-left: 0;
            }
        }
        .form-check-input:checked {
            background-color: #4EB849 !important;
            border-color: #4EB849 !important;
        }
        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .input-icon-wrapper .form-control {
            padding-left: 3rem !important;
            height: 52px;
            border-radius: 12px;
            background-color: #f8fafc;
            border: 1.5px solid #e2e8f0;
            font-weight: 500;
        }
        .input-icon-wrapper .form-control:focus {
            background-color: #ffffff;
            border-color: #0C4DA2;
            box-shadow: 0 0 0 4px rgba(12, 77, 162, 0.1);
        }
        .input-icon-left {
            position: absolute;
            left: 1.15rem;
            color: #94a3b8;
            font-size: 1.1rem;
            pointer-events: none;
            display: flex;
            align-items: center;
        }
        .form-role-pills {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.3rem;
            border-radius: 12px;
        }
        .form-role-pill-btn {
            font-weight: 600;
            border-radius: 10px;
            padding: 0.7rem 1rem;
            color: #64748b;
        }
        .form-role-pill-btn.active {
            background: #0C4DA2 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(12, 77, 162, 0.25) !important;
        }
        .btn-submit-signin {
            height: 52px;
            border-radius: 12px;
            background: #0C4DA2;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(12, 77, 162, 0.2);
            transition: all 0.2s ease;
        }
        .btn-submit-signin:hover {
            background: #093c80;
            transform: translateY(-1px);
        }
        .service-card-box {
            background: #ffffff;
            border-radius: 12px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
    </style>

    <div class="split-login-container">
        
        <!-- LEFT PANEL: Dark Indigo Branding & Highlights -->
        <div class="split-left-panel">
            
            <!-- Grid Dots Overlay Decoration -->
            <div style="position: absolute; top: 3.5rem; right: 2.5rem; opacity: 0.12; display: flex; gap: 6px; flex-direction: column; z-index: 1;">
                <div class="d-flex gap-1.5"><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span></div>
                <div class="d-flex gap-1.5"><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span></div>
                <div class="d-flex gap-1.5"><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span><span class="fw-bold">•</span></div>
            </div>

            <!-- Green Wave SVG Background Decoration -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 220px; overflow: hidden; z-index: 2; pointer-events: none; border-bottom-left-radius: 28px;">
                <svg viewBox="0 0 500 150" preserveAspectRatio="none" style="height: 100%; width: 100%;">
                    <path d="M-10.00,85.00 C150.00,165.00 320.00,20.00 510.00,105.00 L500.00,150.00 L0.00,150.00 Z" style="stroke: none; fill: #4EB849;"></path>
                </svg>
            </div>

            <div style="z-index: 10; position: relative;">
                <!-- Top Institution Banner Logo -->
                <div class="brand-banner-card" style="padding: 0.55rem 1.25rem; background: #ffffff; border-radius: 12px; margin-bottom: 2.5rem; display: inline-block;">
                    <img src="<?php echo BASE_URL; ?>/assets/img/logo.png" alt="Sri Venkateshwaraa" style="max-height: 52px; width: auto; display: block;">
                </div>

                <div class="left-panel-category" style="color: #4EB849; font-weight: 700; letter-spacing: 0.08em; font-size: 0.72rem; margin-bottom: 0.5rem;">DOCUMENT & WORK MANAGEMENT SYSTEM</div>
                <h1 class="left-panel-title" style="font-size: 2.2rem; font-weight: 800; line-height: 1.25; margin-bottom: 1.2rem;">
                    Secure. Centralized.<br>
                    <span style="color: #4EB849;">Organized.</span><br>
                    All in One Place.
                </h1>
                <p class="left-panel-desc" style="color: rgba(255, 255, 255, 0.75); font-size: 0.85rem; line-height: 1.6; margin-bottom: 2.5rem; max-width: 440px;">
                    Upload, organize, and manage institutional work requests, tracking workflows, updates, and real-time status with role-based access.
                </p>

                <!-- Service Grid Cards -->
                <div class="d-flex gap-3 mb-4 flex-wrap">
                    <div class="text-center" style="width: 76px;">
                        <div class="service-card-box">
                            <i class="bi bi-shield-check" style="color: #4EB849; font-size: 1.4rem;"></i>
                        </div>
                        <span style="color: #ffffff; font-size: 0.7rem; font-weight: 600; display: block; line-height: 1.25;">Secure<br>Access</span>
                    </div>
                    <div class="text-center" style="width: 76px;">
                        <div class="service-card-box">
                            <i class="bi bi-folder-fill" style="color: #0C4DA2; font-size: 1.4rem;"></i>
                        </div>
                        <span style="color: #ffffff; font-size: 0.7rem; font-weight: 600; display: block; line-height: 1.25;">Centralized<br>Storage</span>
                    </div>
                    <div class="text-center" style="width: 76px;">
                        <div class="service-card-box">
                            <i class="bi bi-graph-up-arrow" style="color: #4EB849; font-size: 1.2rem;"></i>
                        </div>
                        <span style="color: #ffffff; font-size: 0.7rem; font-weight: 600; display: block; line-height: 1.25;">Status<br>Tracking</span>
                    </div>
                    <div class="text-center" style="width: 76px;">
                        <div class="service-card-box">
                            <i class="bi bi-chat-dots-fill" style="color: #0C4DA2; font-size: 1.3rem;"></i>
                        </div>
                        <span style="color: #ffffff; font-size: 0.7rem; font-weight: 600; display: block; line-height: 1.25;">Live<br>Support</span>
                    </div>
                </div>

                <!-- Unified Metrics White Box -->
                <div style="background: #ffffff; border-radius: 14px; padding: 0.95rem 1.4rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 360px;">
                    <div class="d-flex align-items-center gap-3 flex-fill justify-content-center">
                        <div style="background: rgba(78, 184, 73, 0.1); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-building" style="color: #4EB849; font-size: 1.05rem;"></i>
                        </div>
                        <div class="text-start">
                            <div style="font-size: 1.3rem; font-weight: 800; color: #0C4DA2; line-height: 1; margin-bottom: 2px;"><?php echo max(8, $college_count); ?>+</div>
                            <div style="font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Institutions</div>
                        </div>
                    </div>
                    <div style="width: 1px; height: 30px; background: #e2e8f0; margin: 0 1rem;"></div>
                    <div class="d-flex align-items-center gap-3 flex-fill justify-content-center">
                        <div style="background: rgba(12, 77, 162, 0.1); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-file-earmark-text" style="color: #0C4DA2; font-size: 1.05rem;"></i>
                        </div>
                        <div class="text-start">
                            <div style="font-size: 1.3rem; font-weight: 800; color: #0C4DA2; line-height: 1; margin-bottom: 2px;">&infin;</div>
                            <div style="font-size: 0.65rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Work Records</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer note rendered over curved green wave -->
            <div style="display: flex; align-items: center; gap: 0.65rem; z-index: 10; position: relative; margin-top: auto;">
                <div style="background: rgba(255,255,255,0.18); border: 1.5px solid #ffffff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="bi bi-shield-fill-check" style="color: #ffffff; font-size: 0.95rem;"></i>
                </div>
                <div style="font-size: 0.68rem; color: #ffffff; line-height: 1.4; font-weight: 500;">
                    Developed by Central Management & Digital Marketing &ndash; Sri Venkateshwaraa Group of Institutions, Puducherry.
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Clean White Interactive Login Form -->
        <div class="split-right-panel">
            <div class="mb-3">
                <h2 class="form-header-title">Welcome <span style="color: #4EB849;">Back!</span> 👋</h2>
                <p class="form-header-subtitle">Sign in to continue to your dashboard</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2.5 px-3 small rounded-3 mb-3 border-0 bg-danger bg-opacity-10 text-danger fw-semibold d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2 fs-6"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            <?php display_alert(); ?>

            <!-- Role / Scope Switcher Pills -->
            <div class="form-role-pills mb-4">
                <button type="button" class="form-role-pill-btn active" id="btnRoleAdmin" onclick="setLoginRole('admin')">
                    <i class="bi bi-bank"></i> Central Admin
                </button>
                <button type="button" class="form-role-pill-btn" id="btnRoleCollege" onclick="setLoginRole('college')">
                    <i class="bi bi-mortarboard"></i> College Login
                </button>
            </div>

            <form action="login.php" method="POST" id="loginForm">
                <!-- Hidden input for login type -->
                <input type="hidden" name="login_type" id="login_type" value="system_admin">

                <!-- Dynamic Institution Selection -->
                <div class="form-group-custom" id="collegeSelectGroup" style="display: none;">
                    <label for="college_id_select" class="form-label" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.45rem;">Select Institution / College</label>
                    <select id="college_id_select" class="form-select" onchange="syncCollegeValue(this.value)" style="height: 52px; border-radius: 12px; background-color: #f8fafc; border: 1.5px solid #e2e8f0; font-weight: 500; font-size: 0.925rem;">
                        <option value="">&mdash; Choose Institution &mdash;</option>
                        <?php foreach ($colleges as $col): ?>
                            <option value="<?php echo $col['id']; ?>">
                                <?php echo htmlspecialchars($col['name']); ?> (<?php echo htmlspecialchars($col['code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Email Input -->
                <div class="form-group-custom">
                    <label for="email" class="form-label" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.45rem;">Email Address</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon-left"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control" placeholder="admin" required autofocus>
                    </div>
                </div>
                
                <!-- Password Input with Toggle -->
                <div class="form-group-custom mb-3">
                    <label for="password" class="form-label" style="font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 0.45rem;">Password</label>
                    <div class="input-icon-wrapper">
                        <span class="input-icon-left"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="password-toggle-icon" id="togglePasswordBtn" title="Toggle password visibility" style="position: absolute; right: 1.15rem; color: #94a3b8; cursor: pointer; border: none; background: transparent; padding: 0;">
                            <i class="bi bi-eye-slash" id="togglePasswordIcon" style="font-size: 1.1rem;"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check m-0 d-flex align-items-center gap-1.5">
                        <input class="form-check-input" type="checkbox" id="rememberMe" style="cursor: pointer; width: 17px; height: 17px; border-color: #cbd5e1; border-radius: 4px;">
                        <label class="form-check-label small text-secondary fw-semibold" for="rememberMe" style="cursor: pointer; user-select: none;">
                            Remember Me
                        </label>
                    </div>
                    <a href="#" class="small text-decoration-none fw-bold" style="color: #0C4DA2;">Forgot Password?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit-signin btn w-100 text-white d-flex align-items-center justify-content-center gap-2">
                     Sign In <i class="bi bi-arrow-right-circle-fill" style="font-size: 1.1rem;"></i>
                </button>
            </form>

            <!-- Secure Access Note -->
            <div class="text-center my-4 position-relative">
                <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #e2e8f0; z-index: 1;"></div>
                <span style="position: relative; background: #ffffff; padding: 0 1rem; color: #94a3b8; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; z-index: 2;">
                    Secure & Trusted Access
                </span>
            </div>
            <div class="d-flex align-items-center justify-content-center gap-2 text-muted">
                <i class="bi bi-shield-check text-success" style="font-size: 1.15rem;"></i>
                <span style="font-size: 0.72rem; font-weight: 600; color: #64748b;">
                    Your data is protected with enterprise-grade security
                </span>
            </div>
        </div>

    </div>

    <!-- Bootstrap 5 Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setLoginRole(role) {
            const btnAdmin = document.getElementById('btnRoleAdmin');
            const btnCollege = document.getElementById('btnRoleCollege');
            const collegeGroup = document.getElementById('collegeSelectGroup');
            const loginType = document.getElementById('login_type');
            const collegeSelect = document.getElementById('college_id_select');
            const emailInput = document.getElementById('email');

            if (role === 'admin') {
                btnAdmin.classList.add('active');
                btnCollege.classList.remove('active');
                collegeGroup.style.display = 'none';
                loginType.value = 'system_admin';
                collegeSelect.removeAttribute('required');
                emailInput.placeholder = 'admin';
            } else {
                btnCollege.classList.add('active');
                btnAdmin.classList.remove('active');
                collegeGroup.style.display = 'block';
                collegeSelect.setAttribute('required', 'required');
                loginType.value = collegeSelect.value;
                emailInput.placeholder = 'you@institution.edu';
            }
        }

        function syncCollegeValue(val) {
            document.getElementById('login_type').value = val;
        }

        // Toggle password visibility
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        toggleBtn.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.classList.toggle('bi-eye');
            toggleIcon.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>
