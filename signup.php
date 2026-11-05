<?php
/**
 * SIA 2027 Party Agent Portal - Official Accreditation Registration
 * Self-registration for Adamawa State Polling Unit & Ward Agents
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/audit.php';
require_once __DIR__ . '/lib/auth.php';

start_secure_session();
$pdo = get_db_connection();

// If already authenticated, redirect to dashboard
if (is_authenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$ip = get_client_ip();

// Fetch all 21 Adamawa LGAs for initial dropdown
try {
    $lgas = $pdo->query("SELECT id, name, code FROM lgas ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable) {
    $lgas = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf();

        $name = sanitize($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = sanitize($_POST['phone'] ?? '');
        $state = sanitize($_POST['state'] ?? 'Adamawa State');
        $lga_id = (int)($_POST['lga_id'] ?? 0);
        $ward_id = (int)($_POST['ward_id'] ?? 0);
        $pu_id = (int)($_POST['polling_unit_id'] ?? 0);
        $pu_name_custom = sanitize($_POST['polling_unit_name'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $agreed = !empty($_POST['code_of_conduct']);

        // Basic validations
        if (empty($name) || strlen($name) < 3) {
            throw new Exception('Please enter your full legal name as it appears on your official voter/agent identification.');
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please provide a valid email address.');
        }

        if (empty($phone) || strlen($phone) < 9) {
            throw new Exception('Please provide a valid active phone number for field coordination.');
        }

        if (empty($password) || strlen($password) < 6) {
            throw new Exception('Your password must be at least 6 characters long.');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Password confirmation does not match. Please re-enter.');
        }

        if (!$agreed) {
            throw new Exception('You must review and accept the SIA 2027 Party Agent Code of Conduct to proceed.');
        }

        // Resolve LGA Name
        $lga_name = '';
        if ($lga_id > 0) {
            $lga_stmt = $pdo->prepare("SELECT name FROM lgas WHERE id = ?");
            $lga_stmt->execute([$lga_id]);
            $lga_name = (string)($lga_stmt->fetchColumn() ?: '');
        }
        if (empty($lga_name)) {
            $lga_name = sanitize($_POST['lga_name'] ?? '');
        }
        if (empty($lga_name)) {
            throw new Exception('Please select your assigned Adamawa Local Government Area.');
        }

        // Resolve Ward Name
        $ward_name = '';
        if ($ward_id > 0) {
            $ward_stmt = $pdo->prepare("SELECT name FROM wards WHERE id = ?");
            $ward_stmt->execute([$ward_id]);
            $ward_name = (string)($ward_stmt->fetchColumn() ?: '');
        }
        if (empty($ward_name)) {
            $ward_name = sanitize($_POST['ward_name'] ?? '');
        }
        if (empty($ward_name)) {
            throw new Exception('Please select your assigned Electoral Ward.');
        }

        // Resolve Polling Unit Name
        $pu_name = '';
        if ($pu_id > 0) {
            $pu_stmt = $pdo->prepare("SELECT name, code FROM polling_units WHERE id = ?");
            $pu_stmt->execute([$pu_id]);
            $pu_row = $pu_stmt->fetch();
            if ($pu_row) {
                $pu_name = "{$pu_row['name']} ({$pu_row['code']})";
            }
        }
        if (empty($pu_name) && !empty($pu_name_custom)) {
            $pu_name = $pu_name_custom;
        }
        if (empty($pu_name)) {
            throw new Exception('Please select or specify your assigned Polling Unit.');
        }

        // Rate limiting check on registration to prevent spam/abuse
        if (is_login_rate_limited($pdo, $ip, 'signup_' . $email, 5, 30)) {
            throw new Exception('Too many registration attempts. Please wait 30 minutes before trying again.');
        }

        // Check duplicate email
        $chk = $pdo->prepare("SELECT id FROM agents WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            throw new Exception('An agent account with this email address is already registered. Please sign in or reset your password.');
        }

        // Generate unique Agent Code: SIA-LP-AD-27-XXXX
        $agent_code = '';
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'SIA-LP-AD-27-' . str_pad((string)mt_rand(100, 9999), 4, '0', STR_PAD_LEFT);
            $c_stmt = $pdo->prepare("SELECT id FROM agents WHERE agent_code = ? LIMIT 1");
            $c_stmt->execute([$candidate]);
            if (!$c_stmt->fetch()) {
                $agent_code = $candidate;
                break;
            }
        }
        if (empty($agent_code)) {
            $agent_code = 'SIA-LP-AD-27-' . substr(bin2hex(random_bytes(3)), 0, 4);
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Handle Facial Photograph / Live Snapshot for documentation
        $photo_path = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error uploading facial photograph. Please try capturing the photo again.');
            }
            if ($_FILES['photo']['size'] > 10 * 1024 * 1024) {
                throw new Exception('Facial photograph must not exceed 10MB in size.');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
            finfo_close($finfo);

            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($allowed_mimes[$mime])) {
                throw new Exception('Invalid photograph format. Please upload or snap a JPG, PNG, or WEBP facial photo.');
            }

            $ext = $allowed_mimes[$mime];
            $upload_dir = __DIR__ . '/uploads/agents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $safe_name = 'agent_face_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $upload_dir . $safe_name;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                throw new Exception('Failed to save facial snapshot to server. Please try again.');
            }

            $photo_path = 'uploads/agents/' . $safe_name;
        }

        // Insert agent into database
        $stmt = $pdo->prepare("
            INSERT INTO agents (
                name, email, password_hash, phone, photo, role, 
                state, lga, ward, polling_unit, 
                agent_code, status, lga_id, ward_id, polling_unit_id,
                created_at, last_seen_at
            ) VALUES (
                ?, ?, ?, ?, ?, 'agent', 
                ?, ?, ?, ?, 
                ?, 'pending', ?, ?, ?,
                datetime('now'), datetime('now')
            )
        ");
        $stmt->execute([
            $name,
            $email,
            $password_hash,
            $phone,
            $photo_path,
            $state,
            $lga_name,
            $ward_name,
            $pu_name,
            $agent_code,
            $lga_id ?: null,
            $ward_id ?: null,
            $pu_id ?: null
        ]);

        $new_agent_id = (int)$pdo->lastInsertId();

        // Audit log
        audit_log($pdo, 'agent', $new_agent_id, 'agent_signup_pending', 'agents', $new_agent_id, [
            'ip' => $ip,
            'name' => $name,
            'email' => $email,
            'agent_code' => $agent_code,
            'lga' => $lga_name,
            'ward' => $ward_name
        ]);

        // Establish session
        session_regenerate_id(true);

        $fetch_stmt = $pdo->prepare("SELECT * FROM agents WHERE id = ?");
        $fetch_stmt->execute([$new_agent_id]);
        $agent_data = $fetch_stmt->fetch();
        unset($agent_data['password_hash']);
        $_SESSION['agent'] = $agent_data;

        set_flash('success', "Accreditation application submitted successfully! Your Agent Code is {$agent_code}. Your account is pending coordinator approval before EC8A submission.");

        header('Location: dashboard.php');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Party Agent Accreditation &bull; SIA 2027</title>
  <link rel="icon" type="image/png" href="./assets/images/logos/lp_logo.png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
  <style>
    .material-symbols-outlined {
      font-family: 'Material Symbols Outlined';
      font-weight: normal;
      font-style: normal;
      font-size: 20px;
      line-height: 1;
      display: inline-block;
      vertical-align: middle;
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
  </style>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', '"Outfit"', 'system-ui', 'sans-serif'],
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 flex items-center justify-center p-3 sm:p-6 selection:bg-emerald-600 selection:text-white relative">

  <!-- Background decorative glow -->
  <div class="fixed inset-0 pointer-events-none flex items-center justify-center overflow-hidden -z-10">
    <div class="w-[600px] h-[600px] bg-emerald-500/5 rounded-full blur-3xl transform -translate-y-8"></div>
  </div>

  <div class="w-full max-w-2xl space-y-6 py-6">

    <!-- Header Brand -->
    <div class="text-center space-y-2">
      <a href="index.php" class="inline-flex items-center justify-center group text-decoration-none">
        <div class="w-14 h-14 rounded-2xl bg-white border border-slate-200/90 shadow-md p-1 flex items-center justify-center transition-transform group-hover:scale-105 overflow-hidden">
          <img src="./assets/images/logos/lp_logo.png" alt="Labour Party" class="w-full h-full object-contain">
        </div>
      </a>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 m-0">Agent Registration</h1>
      <p class="text-xs sm:text-sm text-slate-500 font-normal">
        Apply for polling unit agent accreditation &bull; Adamawa 2027
      </p>
    </div>

    <!-- Registration Card -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 space-y-6">

      <?php if ($error): ?>
      <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start gap-2.5">
        <span class="material-symbols-outlined text-red-500 text-base flex-shrink-0 mt-0.5">warning</span>
        <span class="flex-1"><?= htmlspecialchars($error) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="signup.php" id="agentSignupForm" enctype="multipart/form-data" class="space-y-5">
        <?= csrf_field() ?>

        <!-- SECTION 1: Personal Details -->
        <div class="space-y-4">
          <div class="flex items-center gap-2 text-slate-700 text-xs font-semibold uppercase tracking-wider">
            <span class="material-symbols-outlined text-emerald-600 text-base">badge</span>
            <span>Personal Information</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Full Name -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Full Name</label>
              <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">person</span>
                <input 
                  type="text" 
                  name="name" 
                  required 
                  placeholder="e.g. Ibrahim Sani Bello" 
                  value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                  class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-4 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400 font-medium"
                >
              </div>
            </div>

            <!-- Email Address -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Email Address</label>
              <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">mail</span>
                <input 
                  type="email" 
                  name="email" 
                  required 
                  placeholder="name@example.com" 
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                  class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-4 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400 font-medium"
                >
              </div>
            </div>

            <!-- Phone Number -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Phone Number</label>
              <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">phone</span>
                <input 
                  type="tel" 
                  name="phone" 
                  required 
                  placeholder="0803 000 0000" 
                  value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                  class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-4 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400 font-medium"
                >
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 2: Official Documentation & Facial Snapshot -->
        <div class="space-y-4 pt-2 border-t border-slate-100">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-slate-700 text-xs font-semibold uppercase tracking-wider">
              <span class="material-symbols-outlined text-emerald-600 text-base">photo_camera</span>
              <span>Agent Facial Photo / Selfie</span>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
              For Documentation
            </span>
          </div>

          <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row items-center gap-4 sm:gap-6">
              
              <!-- Avatar Preview Container -->
              <div class="relative flex-shrink-0">
                <div id="facePreviewContainer" class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl bg-white border-2 border-dashed border-slate-300 flex flex-col items-center justify-center overflow-hidden shadow-inner group">
                  <span id="facePlaceholderIcon" class="material-symbols-outlined text-slate-300 text-4xl mb-0.5">account_circle</span>
                  <span id="facePlaceholderText" class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">No Photo</span>
                  <img id="facePreviewImg" src="" alt="Face Preview" class="hidden w-full h-full object-cover">
                </div>
                <div id="faceBadgeSuccess" class="hidden absolute -bottom-1.5 -right-1.5 w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-sm">
                  <span class="material-symbols-outlined text-sm font-bold">check</span>
                </div>
              </div>

              <!-- Snapshot Action Triggers -->
              <div class="flex-1 space-y-2 text-center sm:text-left">
                <div>
                  <h4 class="text-xs sm:text-sm font-bold text-slate-900 m-0">Accreditation Portrait Photograph</h4>
                  <p class="text-[11px] sm:text-xs text-slate-500 m-0 mt-0.5 leading-relaxed">
                    Snap a clear front-facing portrait of your face or upload a passport photo. This photo will appear on your Agent Dashboard and central HQ command roster.
                  </p>
                </div>

                <!-- Hidden file inputs: one optimized for live selfie camera, one for photo gallery -->
                <input 
                  type="file" 
                  name="photo" 
                  id="agentCameraInput" 
                  accept="image/*" 
                  capture="user" 
                  onchange="handleFacePhotoSelected(this)" 
                  class="hidden"
                >
                <input 
                  type="file" 
                  id="agentGalleryInput" 
                  accept="image/jpeg,image/png,image/webp" 
                  onchange="handleFacePhotoSelected(this, true)" 
                  class="hidden"
                >

                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 pt-1">
                  <button 
                    type="button" 
                    onclick="document.getElementById('agentCameraInput').click()" 
                    class="px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white inline-flex items-center gap-1.5 shadow-sm transition-all cursor-pointer"
                  >
                    <span class="material-symbols-outlined text-sm">photo_camera</span>
                    <span>Snap Face / Take Selfie</span>
                  </button>

                  <button 
                    type="button" 
                    onclick="document.getElementById('agentGalleryInput').click()" 
                    class="px-3 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 inline-flex items-center gap-1.5 transition-all cursor-pointer"
                  >
                    <span class="material-symbols-outlined text-sm">upload_file</span>
                    <span>Upload Image</span>
                  </button>

                  <button 
                    type="button" 
                    id="clearPhotoBtn" 
                    onclick="clearFacePhoto()" 
                    class="hidden px-2.5 py-2 rounded-xl text-xs font-semibold text-red-600 hover:bg-red-50 transition-all cursor-pointer"
                  >
                    Remove
                  </button>
                </div>

                <div id="photoStatusLabel" class="text-[11px] text-slate-500 font-medium"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 3: Polling Station Assignment -->
        <div class="space-y-4 pt-2 border-t border-slate-100">
          <div class="flex items-center gap-2 text-slate-700 text-xs font-semibold uppercase tracking-wider">
            <span class="material-symbols-outlined text-emerald-600 text-base">location_on</span>
            <span>Polling Station Assignment</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- State -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">State</label>
              <input 
                type="text" 
                name="state" 
                value="Adamawa State" 
                readonly 
                class="w-full bg-slate-100 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-600 cursor-not-allowed font-medium"
              >
            </div>

            <!-- LGA -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                Local Government Area (LGA) <span class="text-red-500">*</span>
              </label>
              <select 
                id="lgaSelect" 
                name="lga_id" 
                required 
                onchange="handleLgaChange(this.value)" 
                class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl px-4 py-3 text-sm text-slate-900 outline-none transition-all cursor-pointer font-medium"
              >
                <option value="">Select LGA</option>
                <?php foreach ($lgas as $lga): ?>
                <option value="<?= (int)$lga['id'] ?>" <?= ((int)($_POST['lga_id'] ?? 0) === (int)$lga['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($lga['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Electoral Ward -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                Electoral Ward <span class="text-red-500">*</span>
              </label>
              <select 
                id="wardSelect" 
                name="ward_id" 
                required 
                disabled
                onchange="handleWardChange(this.value)" 
                class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl px-4 py-3 text-sm text-slate-900 outline-none transition-all cursor-pointer font-medium disabled:opacity-50 disabled:bg-slate-100 disabled:cursor-not-allowed"
              >
                <option value="">Select Ward</option>
              </select>
            </div>

            <!-- Polling Unit -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                Polling Unit (PU) <span class="text-red-500">*</span>
              </label>
              <select 
                id="puSelect" 
                name="polling_unit_id" 
                required 
                disabled
                class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl px-4 py-3 text-sm text-slate-900 outline-none transition-all cursor-pointer font-medium disabled:opacity-50 disabled:bg-slate-100 disabled:cursor-not-allowed"
              >
                <option value="">Select Polling Unit</option>
              </select>
            </div>
          </div>
        </div>

        <!-- SECTION 3: Account Security -->
        <div class="space-y-4 pt-2 border-t border-slate-100">
          <div class="flex items-center gap-2 text-slate-700 text-xs font-semibold uppercase tracking-wider">
            <span class="material-symbols-outlined text-emerald-600 text-base">key</span>
            <span>Security</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Password -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
              <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">lock</span>
                <input 
                  type="password" 
                  id="signupPassword"  
                  name="password" 
                  required 
                  minlength="6"
                  placeholder="Min. 6 characters" 
                  oninput="checkPasswordStrength(this.value)"
                  class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-11 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400"
                >
                <button 
                  type="button" 
                  onclick="toggleSignupPassword()" 
                  class="absolute right-3 text-slate-400 hover:text-slate-600 bg-transparent border-0 p-1 cursor-pointer transition-colors"
                >
                  <span id="signupEyeIcon" class="material-symbols-outlined text-lg">visibility</span>
                </button>
              </div>
              <!-- Strength meter bar -->
              <div class="mt-1.5 flex items-center gap-1.5">
                <div class="h-1 flex-1 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                  <div id="strengthBar" class="h-full w-0 bg-red-500 transition-all duration-300"></div>
                </div>
                <span id="strengthText" class="text-[10px] text-slate-500 font-medium">Too short</span>
              </div>
            </div>

            <!-- Confirm Password -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-1.5">Confirm Password</label>
              <div class="relative flex items-center">
                <span class="material-symbols-outlined absolute left-3.5 text-slate-400 pointer-events-none text-lg">lock_clock</span>
                <input 
                  type="password" 
                  id="confirmPassword" 
                  name="confirm_password" 
                  required 
                  minlength="6"
                  placeholder="Repeat password" 
                  oninput="checkPasswordMatch()"
                  class="w-full bg-white border border-slate-300 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 rounded-xl pl-11 pr-11 py-3 text-sm text-slate-900 outline-none transition-all placeholder:text-slate-400"
                >
                <span id="matchIcon" class="material-symbols-outlined absolute right-3 text-slate-300 text-lg">check_circle</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Confirmation -->
        <div class="pt-1 border-t border-slate-100">
          <label class="flex items-center gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/90 cursor-pointer select-none hover:bg-slate-100/70 transition-colors">
            <input 
              type="checkbox" 
              name="code_of_conduct" 
              value="1" 
              required 
              class="rounded border-slate-300 bg-white text-emerald-600 focus:ring-emerald-500 h-4 w-4 flex-shrink-0"
            >
            <span class="text-xs text-slate-700">
              I certify that I am an accredited party agent and will submit authentic polling unit returns.
            </span>
          </label>
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
          <button 
            type="submit" 
            class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-semibold py-3 rounded-full text-sm shadow-md shadow-emerald-600/20 transition-all cursor-pointer flex items-center justify-center gap-2"
          >
            <span>Create Account</span>
            <span class="material-symbols-outlined text-base">arrow_forward</span>
          </button>
        </div>

      </form>

      <!-- Already Registered Link -->
      <div class="pt-4 border-t border-slate-100 text-center">
        <p class="text-xs text-slate-500 m-0">
          Already have an account? 
          <a href="login.php" class="text-emerald-700 hover:text-emerald-800 font-semibold ml-1 transition-colors">
            Sign in &rarr;
          </a>
        </p>
      </div>

    </div>

    <!-- Quick Footer Links -->
    <div class="text-center text-xs text-slate-500 flex items-center justify-center gap-4">
      <a href="index.php" class="text-slate-500 hover:text-slate-800 transition-colors flex items-center gap-1">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        <span>Back to Website</span>
      </a>
      <span>&bull;</span>
      <a href="login.php" class="text-slate-500 hover:text-slate-800 transition-colors">
        Sign In
      </a>
    </div>

  </div>

  <script>
    // Cascading Dropdowns: LGA -> Wards -> Polling Units
    async function handleLgaChange(lgaId) {
      const wardSelect = document.getElementById('wardSelect');
      const puSelect = document.getElementById('puSelect');

      wardSelect.innerHTML = '<option value="">Loading electoral wards...</option>';
      wardSelect.disabled = true;
      puSelect.innerHTML = '<option value="">-- First Select an Electoral Ward --</option>';
      puSelect.disabled = true;

      if (!lgaId) {
        wardSelect.innerHTML = '<option value="">-- First Select an LGA --</option>';
        return;
      }

      try {
        const res = await fetch(`api.php?action=get_wards&lga_id=${encodeURIComponent(lgaId)}`);
        const data = await res.json();
        
        if (data.success && data.wards && data.wards.length > 0) {
          wardSelect.innerHTML = '<option value="">-- Select Electoral Ward (' + data.wards.length + ' Wards) --</option>';
          data.wards.forEach(w => {
            const opt = document.createElement('option');
            opt.value = w.id;
            opt.textContent = `${w.name} (${w.code || 'Ward'})`;
            wardSelect.appendChild(opt);
          });
          wardSelect.disabled = false;
        } else {
          wardSelect.innerHTML = '<option value="">No wards found for selected LGA</option>';
        }
      } catch (err) {
        console.error('Failed to load wards', err);
        wardSelect.innerHTML = '<option value="">Failed to load wards</option>';
      }
    }

    async function handleWardChange(wardId) {
      const puSelect = document.getElementById('puSelect');

      puSelect.innerHTML = '<option value="">Loading polling units...</option>';
      puSelect.disabled = true;

      if (!wardId) {
        puSelect.innerHTML = '<option value="">-- First Select an Electoral Ward --</option>';
        return;
      }

      try {
        const res = await fetch(`api.php?action=get_pus&ward_id=${encodeURIComponent(wardId)}`);
        const data = await res.json();

        if (data.success && data.polling_units && data.polling_units.length > 0) {
          puSelect.innerHTML = '<option value="">-- Select Polling Unit (' + data.polling_units.length + ' PUs) --</option>';
          data.polling_units.forEach(pu => {
            const opt = document.createElement('option');
            opt.value = pu.id;
            const voters = pu.registered_voters ? ` • ${pu.registered_voters} voters` : '';
            opt.textContent = `${pu.name} (${pu.code || 'PU'}${voters})`;
            puSelect.appendChild(opt);
          });
          puSelect.disabled = false;
        } else {
          puSelect.innerHTML = '<option value="">No polling units found for this ward</option>';
        }
      } catch (err) {
        console.error('Failed to load polling units', err);
        puSelect.innerHTML = '<option value="">Failed to load polling units</option>';
      }
    }

    function toggleSignupPassword() {
      const input = document.getElementById('signupPassword');
      const icon = document.getElementById('signupEyeIcon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility_off';
      } else {
        input.type = 'password';
        icon.textContent = 'visibility';
      }
    }

    function checkPasswordStrength(val) {
      const bar = document.getElementById('strengthBar');
      const text = document.getElementById('strengthText');
      if (!val || val.length === 0) {
        bar.style.width = '0%';
        text.textContent = 'Too short';
        return;
      }
      let score = 0;
      if (val.length >= 6) score += 25;
      if (val.length >= 8) score += 25;
      if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score += 25;
      if (/[0-9]/.test(val) || /[^A-Za-z0-9]/.test(val)) score += 25;

      bar.style.width = `${score}%`;
      if (score <= 25) {
        bar.className = 'h-full bg-red-500 transition-all duration-300';
        text.textContent = 'Weak';
        text.className = 'text-[10px] text-red-400 font-medium';
      } else if (score <= 50) {
        bar.className = 'h-full bg-amber-500 transition-all duration-300';
        text.textContent = 'Fair';
        text.className = 'text-[10px] text-amber-400 font-medium';
      } else if (score <= 75) {
        bar.className = 'h-full bg-blue-500 transition-all duration-300';
        text.textContent = 'Good';
        text.className = 'text-[10px] text-blue-400 font-medium';
      } else {
        bar.className = 'h-full bg-emerald-500 transition-all duration-300';
        text.textContent = 'Strong';
        text.className = 'text-[10px] text-emerald-400 font-medium';
      }
      checkPasswordMatch();
    }

    function checkPasswordMatch() {
      const pwd = document.getElementById('signupPassword').value;
      const confirm = document.getElementById('confirmPassword').value;
      const icon = document.getElementById('matchIcon');
      const text = document.getElementById('matchText');

      if (!confirm) {
        icon.className = 'material-symbols-outlined absolute right-3 text-slate-600 text-lg';
        text.textContent = 'Re-type your password to confirm';
        text.className = 'text-[10px] text-slate-500 mt-1.5 block';
        return;
      }

      if (pwd === confirm) {
        icon.className = 'material-symbols-outlined absolute right-3 text-emerald-400 text-lg';
        text.textContent = 'Passwords match';
        text.className = 'text-[10px] text-emerald-400 mt-1.5 block';
      } else {
        icon.className = 'material-symbols-outlined absolute right-3 text-red-400 text-lg';
        text.textContent = 'Passwords do not match';
        text.className = 'text-[10px] text-red-400 mt-1.5 block';
      }
    }

    function handleFacePhotoSelected(input, isGallery = false) {
      if (!input.files || !input.files[0]) return;
      const file = input.files[0];
      
      const cameraInput = document.getElementById('agentCameraInput');
      const galleryInput = document.getElementById('agentGalleryInput');
      if (isGallery) {
        try {
          const dt = new DataTransfer();
          dt.items.add(file);
          cameraInput.files = dt.files;
        } catch (e) {
          cameraInput.removeAttribute('name');
          galleryInput.setAttribute('name', 'photo');
        }
      } else {
        cameraInput.setAttribute('name', 'photo');
        galleryInput.removeAttribute('name');
      }

      const reader = new FileReader();
      reader.onload = function(e) {
        const previewImg = document.getElementById('facePreviewImg');
        const placeholderIcon = document.getElementById('facePlaceholderIcon');
        const placeholderText = document.getElementById('facePlaceholderText');
        const badgeSuccess = document.getElementById('faceBadgeSuccess');
        const clearBtn = document.getElementById('clearPhotoBtn');
        const statusLabel = document.getElementById('photoStatusLabel');

        previewImg.src = e.target.result;
        previewImg.classList.remove('hidden');
        placeholderIcon.classList.add('hidden');
        placeholderText.classList.add('hidden');
        badgeSuccess.classList.remove('hidden');
        clearBtn.classList.remove('hidden');

        const sizeKb = Math.round(file.size / 1024);
        statusLabel.innerHTML = `<span class="text-emerald-700 font-bold inline-flex items-center gap-1"><span class="material-symbols-outlined text-xs">check_circle</span> Portrait attached: ${escapeHtml(file.name)} (${sizeKb} KB)</span>`;
      };
      reader.readAsDataURL(file);
    }

    function clearFacePhoto() {
      const cameraInput = document.getElementById('agentCameraInput');
      const galleryInput = document.getElementById('agentGalleryInput');
      cameraInput.value = '';
      galleryInput.value = '';
      cameraInput.setAttribute('name', 'photo');
      galleryInput.removeAttribute('name');

      const previewImg = document.getElementById('facePreviewImg');
      const placeholderIcon = document.getElementById('facePlaceholderIcon');
      const placeholderText = document.getElementById('facePlaceholderText');
      const badgeSuccess = document.getElementById('faceBadgeSuccess');
      const clearBtn = document.getElementById('clearPhotoBtn');
      const statusLabel = document.getElementById('photoStatusLabel');

      previewImg.src = '';
      previewImg.classList.add('hidden');
      placeholderIcon.classList.remove('hidden');
      placeholderText.classList.remove('hidden');
      badgeSuccess.classList.add('hidden');
      clearBtn.classList.add('hidden');
      statusLabel.textContent = '';
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
      });
    }
  </script>

</body>
</html>

