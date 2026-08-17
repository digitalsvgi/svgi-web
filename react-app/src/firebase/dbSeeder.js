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

/**
 * Seeds base colleges and departments collections in Firestore
 */
export async function seedFirestore() {
  try {
    // 1. Check if database is already seeded
    const colRef = collection(db, 'colleges');
    const q = query(colRef, limit(1));
    const querySnapshot = await getDocs(q);
    if (!querySnapshot.empty) {
      return { success: true, message: "Database is already seeded." };
    }

    const batch = writeBatch(db);

    // 2. Insert Colleges and Departments
    for (const cData of collegesData) {
      // Create college document reference with custom ID matching its code
      const collegeDocRef = doc(db, 'colleges', cData.code);
      batch.set(collegeDocRef, {
        name: cData.name,
        code: cData.code,
        email: cData.email,
        status: 'active'
      });

      // Create departments under each college
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
    return { success: true, message: "Colleges and departments seeded successfully." };
  } catch (error) {
    console.error("Firestore seeding failed:", error);
    return { success: false, message: error.message };
  }
}
