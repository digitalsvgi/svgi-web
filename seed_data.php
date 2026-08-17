<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

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

try {
    // Clean up existing colleges, departments, and college users to ensure clean replacement
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE colleges;");
    $pdo->exec("TRUNCATE TABLE departments;");
    $pdo->exec("DELETE FROM users WHERE role = 'college_user';");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $pdo->beginTransaction();

    foreach ($colleges_data as $cData) {
        // 1. Insert College
        $stmt = $pdo->prepare("INSERT INTO colleges (name, code, email, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$cData['name'], $cData['code'], $cData['email']]);
        $college_id = $pdo->lastInsertId();

        if ($college_id) {
            // 2. Insert Departments
            foreach ($cData['departments'] as $deptName) {
                $deptStmt = $pdo->prepare("INSERT INTO departments (college_id, name, status) VALUES (?, ?, 'active')");
                $deptStmt->execute([$college_id, $deptName]);
            }

            // 3. Insert User
            $hashedPassword = password_hash($cData['password'], PASSWORD_BCRYPT);
            $userName = "Admin - " . $cData['code'];
            $userStmt = $pdo->prepare("INSERT INTO users (college_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'college_user', 'active')");
            $userStmt->execute([$college_id, $userName, $cData['email'], $hashedPassword]);
        }
    }

    $pdo->commit();
    echo "Seed data completed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
