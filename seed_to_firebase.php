<?php
// seed_to_firebase.php
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/plain; charset=utf-8');

// 1. Read Firebase config keys from react-app/.env
$apiKey = '';
$projectId = '';

$envPath = __DIR__ . '/react-app/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if ($key === 'VITE_FIREBASE_API_KEY') $apiKey = $val;
            if ($key === 'VITE_FIREBASE_PROJECT_ID') $projectId = $val;
        }
    }
}

if (empty($apiKey) || empty($projectId)) {
    die("Error: Firebase API Key or Project ID not found in react-app/.env. Please run setup first.\n");
}

echo "Detected Firebase Project: $projectId\n";
echo "Starting data synchronization...\n\n";

$colleges_data = [
  [
      'name' => 'Sri Venkateshwaraa Medical College Hospital & Research Centre',
      'code' => 'SVMCHRC',
      'email' => 'venkateshwaraa1@gmail.com',
      'password' => 'password@1',
      'departments' => [
          'Anatomy', 'Biochemistry', 'Physiology', 'Community Medicine', 'Tobacco Cessation Centre',
          'Forensic Medicine', 'Microbiology', 'Pharmacology', 'Pathology', 'Anesthesiology', 'DVL', 'ENT',
          'General Medicine', 'General Surgery', 'OBG', 'Radiology', 'Orthopaedics', 'Psychiatry',
          'Ophthalmology', 'Paediatrics', 'Pulmonary Medicine', 'Cardiology', 'CTVS', 'Urology', 'Nephrology',
          'Neurology', 'Neurosurgery', 'Surgical Oncology', 'Surgical Gastroenterology', 'Plastic Surgery',
          'Pediatric Surgery', 'Neonatology', 'Vascular Surgery', 'Diabetes Clinic', 'Clinical Oncology', 'OG'
      ]
  ],
  [
      'name' => 'Sri Venkateshwaraa Dental College',
      'code' => 'SVDC',
      'email' => 'venkateshwaraa2@gmail.com',
      'password' => 'password@2',
      'departments' => [
          'Orthodontics And Dentofacial Orthopaedics', 'Prosthodontics', 'Conservative Dentistry & Endodontic',
          'Oral And Maxillofacial Surgery', 'Periodontics & Oral Implantology', 'Oral Pathology',
          'Public Health Dentistry', 'Pedodontics & Preventive Dentistry', 'Oral Medicine & Radiology'
      ]
  ],
  [
      'name' => 'Indirani College of Nursing',
      'code' => 'ICON',
      'email' => 'venkateshwaraa3@gmail.com',
      'password' => 'password@3',
      'departments' => [
          'Medical Surgical Nursing', 'Child Health Nursing', 'OBG Nursing', 'Community Health Nursing',
          'Mental Health Nursing', 'Highlights', 'Department Facilities'
      ]
  ],
  [
      'name' => 'Sri Venkateshwaraa College of Physiotherapy',
      'code' => 'SVCP',
      'email' => 'venkateshwaraa4@gmail.com',
      'password' => 'password@4',
      'departments' => ['Physiotherapy']
  ],
  [
      'name' => 'Sri Venkateshwaraa College of Paramedical Sciences',
      'code' => 'SVCPS',
      'email' => 'venkateshwaraa5@gmail.com',
      'password' => 'password@5',
      'departments' => ['Paramedical Sciences']
  ],
  [
      'name' => 'Sri Venkateshwaraa College of Pharmacy',
      'code' => 'SVCPH',
      'email' => 'venkateshwaraa6@gmail.com',
      'password' => 'password@6',
      'departments' => ['Pharmacology', 'Pharmaceutics', 'Pharmaceutical Chemistry', 'Pharmacognosy', 'Pharmacy Practice']
  ],
  [
      'name' => 'Sri Venkateshwaraa College of Engineering & Technology',
      'code' => 'SVCET',
      'email' => 'venkateshwaraa7@gmail.com',
      'password' => 'password@7',
      'departments' => [
          'BIO MEDICAL', 'CSE', 'ECE', 'EEE', 'MBA', 'MECHANICAL', 'AI and DS', 'IOT Cyber Security and Blockchain Technology'
      ]
  ],
  [
      'name' => 'Sri Venkateshwaraa College of Hospital',
      'code' => 'SVCH',
      'email' => 'venkateshwaraa8@gmail.com',
      'password' => 'password@8',
      'departments' => [
          'Cardiology', 'Neurology', 'Nephrology', 'Diabetology', 'Fertility Clinic', 'CTVS', 'Neurosurgery',
          'Clinical Oncology', 'Surgical Oncology', 'Plastic & Reconstructive Surgery', 'Emergency Medicine',
          'General Medicine', 'General Surgery', 'Surgical Gastroenterology', 'Orthopaedics', 'Obstetrics & Gynaecology',
          'Paediatrics', 'Neonatology', 'Pediatric Surgery', 'ENT', 'Ophthalmology', 'Dental', 'Physiotherapy',
          'Psychiatry', 'Cosmetic Dermatology', 'Anesthesia', 'Radio Diagnosis', 'TB & Chest', 'Urology', 'Vascular Surgery'
      ]
  ]
];

$systemUsers = [
  ['name' => 'Super Admin User', 'email' => 'superadmin@example.com', 'password' => 'admin123', 'role' => 'super_admin', 'collegeId' => null],
  ['name' => 'Admin User', 'email' => 'admin@example.com', 'password' => 'admin123', 'role' => 'admin', 'collegeId' => null],
  ['name' => 'Admin - SVMCHRC', 'email' => 'venkateshwaraa1@gmail.com', 'password' => 'password@1', 'role' => 'college_user', 'collegeId' => 'SVMCHRC'],
  ['name' => 'Admin - SVDC', 'email' => 'venkateshwaraa2@gmail.com', 'password' => 'password@2', 'role' => 'college_user', 'collegeId' => 'SVDC'],
  ['name' => 'Admin - ICON', 'email' => 'venkateshwaraa3@gmail.com', 'password' => 'password@3', 'role' => 'college_user', 'collegeId' => 'ICON'],
  ['name' => 'Admin - SVCP', 'email' => 'venkateshwaraa4@gmail.com', 'password' => 'password@4', 'role' => 'college_user', 'collegeId' => 'SVCP'],
  ['name' => 'Admin - SVCPS', 'email' => 'venkateshwaraa5@gmail.com', 'password' => 'password@5', 'role' => 'college_user', 'collegeId' => 'SVCPS'],
  ['name' => 'Admin - SVCPH', 'email' => 'venkateshwaraa6@gmail.com', 'password' => 'password@6', 'role' => 'college_user', 'collegeId' => 'SVCPH'],
  ['name' => 'Admin - SVCET', 'email' => 'venkateshwaraa7@gmail.com', 'password' => 'password@7', 'role' => 'college_user', 'collegeId' => 'SVCET'],
  ['name' => 'Admin - SVCH', 'email' => 'venkateshwaraa8@gmail.com', 'password' => 'password@8', 'role' => 'college_user', 'collegeId' => 'SVCH']
];

// Helper to make HTTP POST requests
function restPost($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    if ($response === false) {
        $error_msg = curl_error($ch);
        echo "[cURL Error: $error_msg] ";
    }
    curl_close($ch);
    return json_decode($response, true);
}

// Helper to make HTTP PATCH requests (Firestore write)
function restPatch($url, $data, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Enable implicit flushing
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();


// 2. Create Auth Accounts & User Profiles
echo "--- Seeding Users into Firebase Auth & Firestore ---\n";
$superAdminToken = '';

foreach ($systemUsers as $u) {
    echo "Processing user: {$u['email']}... ";
    
    // Register user in Firebase Authentication
    $authUrl = "https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=" . $apiKey;
    $authRes = restPost($authUrl, [
        'email' => $u['email'],
        'password' => $u['password'],
        'returnSecureToken' => true
    ]);
    
    if (isset($authRes['error'])) {
        // If already exists, attempt to log in to get token
        if ($authRes['error']['message'] === 'EMAIL_EXISTS') {
            $loginUrl = "https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=" . $apiKey;
            $authRes = restPost($loginUrl, [
                'email' => $u['email'],
                'password' => $u['password'],
                'returnSecureToken' => true
            ]);
        }
    }

    if (isset($authRes['idToken'])) {
        $uid = $authRes['localId'];
        $token = $authRes['idToken'];
        
        if ($u['role'] === 'super_admin') {
            $superAdminToken = $token;
        }

        // Save profile in Firestore users collection
        $firestoreUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/users/{$uid}?key=" . $apiKey;
        $profileData = [
            'fields' => [
                'name' => ['stringValue' => $u['name']],
                'email' => ['stringValue' => $u['email']],
                'role' => ['stringValue' => $u['role']],
                'collegeId' => $u['collegeId'] ? ['stringValue' => $u['collegeId']] : ['nullValue' => null],
                'status' => ['stringValue' => 'active']
            ]
        ];
        
        $profileRes = restPatch($firestoreUrl, $profileData, $token);
        if (isset($profileRes['error'])) {
            echo "Failed to save profile: " . $profileRes['error']['message'] . "\n";
        } else {
            echo "SUCCESS\n";
        }
    } else {
        echo "FAILED: " . ($authRes['error']['message'] ?? 'Unknown Auth Error') . "\n";
    }
}

if (empty($superAdminToken)) {
    die("\nError: Could not retrieve Super Admin ID Token. Sync aborted.\n");
}

// 3. Seed Colleges and Departments
echo "\n--- Seeding Colleges & Departments ---\n";
foreach ($colleges_data as $c) {
    echo "Seeding College: {$c['code']}... ";
    
    // Write College Document
    $collegeUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/colleges/{$c['code']}?key=" . $apiKey;
    $collegeData = [
        'fields' => [
            'name' => ['stringValue' => $c['name']],
            'code' => ['stringValue' => $c['code']],
            'email' => ['stringValue' => $c['email']],
            'status' => ['stringValue' => 'active']
        ]
    ];
    $colRes = restPatch($collegeUrl, $collegeData, $superAdminToken);
    
    if (isset($colRes['error'])) {
        echo "FAILED: " . $colRes['error']['message'] . "\n";
    } else {
        echo "SUCCESS\n";
    }

    // Write Department Documents
    foreach ($c['departments'] as $dept) {
        $deptDocId = $c['code'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $dept);
        $deptUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/departments/{$deptDocId}?key=" . $apiKey;
        $deptData = [
            'fields' => [
                'collegeId' => ['stringValue' => $c['code']],
                'name' => ['stringValue' => $dept],
                'status' => ['stringValue' => 'active']
            ]
        ];
        restPatch($deptUrl, $deptData, $superAdminToken);
    }
}

echo "\nData synchronization completed successfully!\n";
?>
