import React, { useEffect, useState } from 'react';
import { db } from '../firebase/firebaseConfig';
import { collection, onSnapshot, getDocs } from 'firebase/firestore';
import { useAuth } from '../context/AuthContext';
import { seedFirestore } from '../firebase/dbSeeder';

export default function DashboardView() {
  const { currentUser } = useAuth();
  const [stats, setStats] = useState({
    colleges: 0,
    departments: 0,
    submissions: 0,
    pending: 0,
    processing: 0,
    completed: 0
  });

  const [seedingResult, setSeedingResult] = useState('');
  const [seedingLoading, setSeedingLoading] = useState(false);

  useEffect(() => {
    // Set up real-time listener for submissions counts
    const unsubSub = onSnapshot(collection(db, 'submissions'), (snapshot) => {
      let pending = 0, processing = 0, completed = 0;
      snapshot.forEach(doc => {
        const data = doc.data();
        if (data.status === 'completed') completed++;
        else if (data.status === 'processing') processing++;
        else pending++;
      });

      setStats(prev => ({
        ...prev,
        submissions: snapshot.size,
        pending,
        processing,
        completed
      }));
    });

    // Load static counts
    const loadCounts = async () => {
      try {
        const colSnap = await getDocs(collection(db, 'colleges'));
        const deptSnap = await getDocs(collection(db, 'departments'));
        setStats(prev => ({
          ...prev,
          colleges: colSnap.size,
          departments: deptSnap.size
        }));
      } catch (e) {
        console.error(e);
      }
    };
    loadCounts();

    return () => unsubSub();
  }, []);

  const handleSeed = async () => {
    try {
      setSeedingLoading(true);
      setSeedingResult('');
      const res = await seedFirestore();
      setSeedingResult(res.message);
    } catch (e) {
      setSeedingResult('Failed to run seeding: ' + e.message);
    } finally {
      setSeedingLoading(false);
    }
  };

  return (
    <div className="container-fluid py-4">
      {/* Welcome Banner */}
      <div className="card border-0 rounded-4 shadow-sm mb-4 p-4 text-white" style={{
        backgroundImage: 'linear-gradient(135deg, #0C4DA2 0%, #08346e 100%)'
      }}>
        <h4 className="fw-bold mb-1">Welcome back, {currentUser?.name}!</h4>
        <p className="mb-0 text-white-50 small">Monitor your institution's submissions, departments, and operations metrics live.</p>
      </div>

      {/* Metrics Row */}
      <div className="row g-4 mb-4">
        <div className="col-12 col-md-6 col-lg-3">
          <div className="card border-0 rounded-4 shadow-sm p-3 bg-white">
            <div className="d-flex align-items-center gap-3">
              <div className="p-3 rounded-4 bg-light text-primary" style={{ color: '#0C4DA2' }}>
                <i className="bi bi-bank fs-4"></i>
              </div>
              <div>
                <h5 className="fw-bold mb-0">{stats.colleges}</h5>
                <span className="small text-muted">Active Colleges</span>
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-md-6 col-lg-3">
          <div className="card border-0 rounded-4 shadow-sm p-3 bg-white">
            <div className="d-flex align-items-center gap-3">
              <div className="p-3 rounded-4 bg-light text-success" style={{ color: '#4EB849' }}>
                <i className="bi bi-diagram-3 fs-4"></i>
              </div>
              <div>
                <h5 className="fw-bold mb-0">{stats.departments}</h5>
                <span className="small text-muted">Departments</span>
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-md-6 col-lg-3">
          <div className="card border-0 rounded-4 shadow-sm p-3 bg-white">
            <div className="d-flex align-items-center gap-3">
              <div className="p-3 rounded-4 bg-light text-warning" style={{ color: '#f59e0b' }}>
                <i className="bi bi-file-earmark-check fs-4"></i>
              </div>
              <div>
                <h5 className="fw-bold mb-0">{stats.submissions}</h5>
                <span className="small text-muted">Total Submissions</span>
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-md-6 col-lg-3">
          <div className="card border-0 rounded-4 shadow-sm p-3 bg-white">
            <div className="d-flex align-items-center gap-3">
              <div className="p-3 rounded-4 bg-light text-danger" style={{ color: '#ef4444' }}>
                <i className="bi bi-exclamation-octagon fs-4"></i>
              </div>
              <div>
                <h5 className="fw-bold mb-0">{stats.pending}</h5>
                <span className="small text-muted">Pending Audits</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Database Seeder Button (Super Admin Only) */}
      {currentUser?.role === 'super_admin' && (
        <div className="card border-0 rounded-4 shadow-sm mb-4 p-4 bg-white">
          <h5 className="fw-bold text-dark mb-2">Firestore Seeding Utility</h5>
          <p className="text-muted small">Use this utility to automatically populate your new Cloud Firestore database with the 8 Colleges and 97 Departments configured in the SVGI system rules.</p>
          <div className="d-flex align-items-center gap-3">
            <button
              onClick={handleSeed}
              disabled={seedingLoading}
              className="btn btn-primary fw-bold px-4 py-2"
              style={{ backgroundColor: '#0C4DA2', borderColor: '#0C4DA2', borderRadius: '10px' }}
            >
              {seedingLoading ? 'Seeding...' : 'Seed Colleges & Departments'}
            </button>
            {seedingResult && (
              <span className={`small fw-bold ${seedingResult.includes('already') ? 'text-warning' : 'text-success'}`}>
                {seedingResult}
              </span>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
