import { db } from './firebaseConfig';
import { 
  collection, 
  doc, 
  writeBatch, 
  getDocs, 
  query, 
  limit 
} from 'firebase/firestore';

const collegesData = [
  {
    name: 'Sri Venkateshwaraa Medical College Hospital & Research Centre',
    code: 'SVMCHRC',
    email: 'venkateshwaraa1@gmail.com',
    departments: [
      'Anatomy', 'Biochemistry', 'Physiology', 'Community Medicine', 'Tobacco Cessation Centre',
      'Forensic Medicine', 'Microbiology', 'Pharmacology', 'Pathology', 'Anesthesiology', 'DVL', 'ENT',
      'General Medicine', 'General Surgery', 'OBG', 'Radiology', 'Orthopaedics', 'Psychiatry',
      'Ophthalmology', 'Paediatrics', 'Pulmonary Medicine', 'Cardiology', 'CTVS', 'Urology', 'Nephrology',
      'Neurology', 'Neurosurgery', 'Surgical Oncology', 'Surgical Gastroenterology', 'Plastic Surgery',
      'Pediatric Surgery', 'Neonatology', 'Vascular Surgery', 'Diabetes Clinic', 'Clinical Oncology', 'OG'
    ]
  },
  {
    name: 'Sri Venkateshwaraa Dental College',
    code: 'SVDC',
    email: 'venkateshwaraa2@gmail.com',
    departments: [
      'Orthodontics And Dentofacial Orthopaedics', 'Prosthodontics', 'Conservative Dentistry & Endodontic',
      'Oral And Maxillofacial Surgery', 'Periodontics & Oral Implantology', 'Oral Pathology',
      'Public Health Dentistry', 'Pedodontics & Preventive Dentistry', 'Oral Medicine & Radiology'
    ]
  },
  {
    name: 'Indirani College of Nursing',
    code: 'ICON',
    email: 'venkateshwaraa3@gmail.com',
    departments: [
      'Medical Surgical Nursing', 'Child Health Nursing', 'OBG Nursing', 'Community Health Nursing',
      'Mental Health Nursing', 'Highlights', 'Department Facilities'
    ]
  },
  {
    name: 'Sri Venkateshwaraa College of Physiotherapy',
    code: 'SVCP',
    email: 'venkateshwaraa4@gmail.com',
    departments: ['Physiotherapy']
  },
  {
    name: 'Sri Venkateshwaraa College of Paramedical Sciences',
    code: 'SVCPS',
    email: 'venkateshwaraa5@gmail.com',
    departments: ['Paramedical Sciences']
  },
  {
    name: 'Sri Venkateshwaraa College of Pharmacy',
    code: 'SVCPH',
    email: 'venkateshwaraa6@gmail.com',
    departments: ['Pharmacology', 'Pharmaceutics', 'Pharmaceutical Chemistry', 'Pharmacognosy', 'Pharmacy Practice']
  },
  {
    name: 'Sri Venkateshwaraa College of Engineering & Technology',
    code: 'SVCET',
    email: 'venkateshwaraa7@gmail.com',
    departments: [
      'BIO MEDICAL', 'CSE', 'ECE', 'EEE', 'MBA', 'MECHANICAL', 'AI and DS', 'IOT Cyber Security and Blockchain Technology'
    ]
  },
  {
    name: 'Sri Venkateshwaraa College of Hospital',
    code: 'SVCH',
    email: 'venkateshwaraa8@gmail.com',
    departments: [
      'Cardiology', 'Neurology', 'Nephrology', 'Diabetology', 'Fertility Clinic', 'CTVS', 'Neurosurgery',
      'Clinical Oncology', 'Surgical Oncology', 'Plastic & Reconstructive Surgery', 'Emergency Medicine',
      'General Medicine', 'General Surgery', 'Surgical Gastroenterology', 'Orthopaedics', 'Obstetrics & Gynaecology',
      'Paediatrics', 'Neonatology', 'Pediatric Surgery', 'ENT', 'Ophthalmology', 'Dental', 'Physiotherapy',
      'Psychiatry', 'Cosmetic Dermatology', 'Anesthesia', 'Radio Diagnosis', 'TB & Chest', 'Urology', 'Vascular Surgery'
    ]
  }
];

const systemUsers = [
  { name: 'Super Admin User', email: 'superadmin@example.com', password: 'admin123', role: 'super_admin', collegeId: null },
  { name: 'Admin User', email: 'admin@example.com', password: 'admin123', role: 'admin', collegeId: null },
  { name: 'Admin - SVMCHRC', email: 'venkateshwaraa1@gmail.com', password: 'password@1', role: 'college_user', collegeId: 'SVMCHRC' },
  { name: 'Admin - SVDC', email: 'venkateshwaraa2@gmail.com', password: 'password@2', role: 'college_user', collegeId: 'SVDC' },
  { name: 'Admin - ICON', email: 'venkateshwaraa3@gmail.com', password: 'password@3', role: 'college_user', collegeId: 'ICON' },
  { name: 'Admin - SVCP', email: 'venkateshwaraa4@gmail.com', password: 'password@4', role: 'college_user', collegeId: 'SVCP' },
  { name: 'Admin - SVCPS', email: 'venkateshwaraa5@gmail.com', password: 'password@5', role: 'college_user', collegeId: 'SVCPS' },
  { name: 'Admin - SVCPH', email: 'venkateshwaraa6@gmail.com', password: 'password@6', role: 'college_user', collegeId: 'SVCPH' },
  { name: 'Admin - SVCET', email: 'venkateshwaraa7@gmail.com', password: 'password@7', role: 'college_user', collegeId: 'SVCET' },
  { name: 'Admin - SVCH', email: 'venkateshwaraa8@gmail.com', password: 'password@8', role: 'college_user', collegeId: 'SVCH' }
];

/**
 * Seeds base colleges, departments, and users collections in Firestore & Firebase Auth
 */
export async function seedFirestore() {
  try {
    // 1. Check if database is already seeded with colleges
    const colRef = collection(db, 'colleges');
    const q = query(colRef, limit(1));
    const querySnapshot = await getDocs(q);
    
    // Seed colleges and departments if empty
    if (querySnapshot.empty) {
      const batch = writeBatch(db);

      for (const cData of collegesData) {
        // Create college doc
        const collegeDocRef = doc(db, 'colleges', cData.code);
        batch.set(collegeDocRef, {
          name: cData.name,
          code: cData.code,
          email: cData.email,
          status: 'active'
        });

        // Create department docs under each college
        for (const deptName of cData.departments) {
          const deptDocId = `${cData.code}_${deptName.replace(/[^a-zA-Z0-9]/g, '_')}`;
          const deptDocRef = doc(db, 'departments', deptDocId);
          batch.set(deptDocRef, {
            collegeId: cData.code,
            name: deptName,
            status: 'active'
          });
        }
      }
      await batch.commit();
    }

    // 2. Register and Seed all Admin & College Users in Firebase Auth + Firestore
    const functionsUrl = import.meta.env.VITE_CLOUD_FUNCTIONS_URL || 'https://us-central1-svgi-cwm-portal.cloudfunctions.net';
    let userFailures = 0;

    for (const u of systemUsers) {
      try {
        const response = await fetch(`${functionsUrl}/createSystemUser`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(u)
        });
        const resData = await response.json();
        if (!resData.success) {
          console.error(`Failed to register ${u.email}:`, resData.message);
          userFailures++;
        }
      } catch (err) {
        console.error(`Network error registering user ${u.email}:`, err);
        userFailures++;
      }
    }

    if (userFailures > 0) {
      return { 
        success: false, 
        message: `Database seeded, but ${userFailures} users failed to sync. Make sure your Firebase Cloud Functions are deployed.` 
      };
    }

    return { 
      success: true, 
      message: "Colleges, departments, and auth users seeded successfully." 
    };
  } catch (error) {
    console.error("Firestore seeding failed:", error);
    return { success: false, message: error.message };
  }
}
