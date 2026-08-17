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

  const [collegesList, setCollegesList] = useState([]);
  const [submissionsList, setSubmissionsList] = useState([]);
  const [seedingResult, setSeedingResult] = useState('');
  const [seedingLoading, setSeedingLoading] = useState(false);

  useEffect(() => {
    // Real-time submissions listener
    const unsubSub = onSnapshot(collection(db, 'submissions'), (snapshot) => {
      const list = [];
      let pending = 0, processing = 0, completed = 0;
      snapshot.forEach(doc => {
        const data = doc.data();
        list.push({ id: doc.id, ...data });
        if (data.status === 'completed') completed++;
        else if (data.status === 'processing') processing++;
        else pending++;
      });

      setSubmissionsList(list);
      setStats(prev => ({
        ...prev,
        submissions: snapshot.size,
        pending,
        processing,
        completed
      }));
    });

    // Real-time colleges listener
    const unsubCol = onSnapshot(collection(db, 'colleges'), (snapshot) => {
      const list = [];
      snapshot.forEach(doc => {
        list.push({ id: doc.id, ...doc.data() });
      });
      setCollegesList(list);
      setStats(prev => ({
        ...prev,
        colleges: snapshot.size
      }));
    });

    // Load static department count
    const loadDeptCount = async () => {
      try {
        const deptSnap = await getDocs(collection(db, 'departments'));
        setStats(prev => ({
          ...prev,
          departments: deptSnap.size
        }));
      } catch (e) {
        console.error(e);
      }
    };
    loadDeptCount();

    return () => {
      unsubSub();
      unsubCol();
    };
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

  // Compile college-wise stats list matching Screenshot 2
  const getCollegesStats = () => {
    return collegesList.map(col => {
      const colSubs = submissionsList.filter(s => s.collegeId === col.id);
      return {
        id: col.id,
        name: col.name,
        total: colSubs.length,
        pending: colSubs.filter(s => s.status === 'pending').length,
        processing: colSubs.filter(s => s.status === 'processing').length,
        completed: colSubs.filter(s => s.status === 'completed').length
      };
    });
  };

  const collegeStatsData = getCollegesStats();

  return (
    <div className="container-fluid py-2">
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

      {/* College-wise Submission Statistics Grid (Screenshot 2) */}
      <div className="card border-0 rounded-4 shadow-sm mb-4 p-4 bg-white">
        <h5 className="fw-bold text-dark mb-3" style={{ fontSize: '1.1rem' }}>College Submission Metrics Overview</h5>
        <div className="table-responsive">
          <table className="table align-middle table-hover m-0">
            <thead className="table-light">
              <tr style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: '#64748b' }}>
                <th style={{ padding: '1rem' }}>College Name</th>
                <th className="text-center" style={{ padding: '1rem', width: '100px' }}>Total</th>
                <th className="text-center" style={{ padding: '1rem', width: '100px' }}>Pending</th>
                <th className="text-center" style={{ padding: '1rem', width: '100px' }}>Processing</th>
                <th className="text-center" style={{ padding: '1rem', width: '100px' }}>Completed</th>
              </tr>
            </thead>
            <tbody>
              {collegeStatsData.length === 0 ? (
                <tr>
                  <td colSpan="5" className="text-center text-muted py-4 small">No college metrics found. Please seed data.</td>
                </tr>
              ) : (
                collegeStatsData.map((col) => (
                  <tr key={col.id}>
                    <td className="fw-bold text-dark" style={{ padding: '1.1rem 1rem', fontSize: '0.9rem' }}>{col.name}</td>
                    <td className="text-center" style={{ padding: '1.1rem 1rem' }}>
                      <span className="badge rounded-pill bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-1.5" style={{ fontSize: '0.8rem' }}>
                        {col.total}
                      </span>
                    </td>
                    <td className="text-center" style={{ padding: '1.1rem 1rem' }}>
                      <span className="badge rounded-pill bg-danger bg-opacity-10 text-danger fw-bold px-3 py-1.5" style={{ fontSize: '0.8rem' }}>
                        {col.pending}
                      </span>
                    </td>
                    <td className="text-center" style={{ padding: '1.1rem 1rem' }}>
                      <span className="badge rounded-pill bg-warning bg-opacity-10 text-warning fw-bold px-3 py-1.5" style={{ fontSize: '0.8rem' }}>
                        {col.processing}
                      </span>
                    </td>
                    <td className="text-center" style={{ padding: '1.1rem 1rem' }}>
                      <span className="badge rounded-pill bg-success bg-opacity-10 text-success fw-bold px-3 py-1.5" style={{ fontSize: '0.8rem' }}>
                        {col.completed}
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Database Seeder Button (Super Admin Only) */}
      {currentUser?.role === 'super_admin' && (
        <div className="card border-0 rounded-4 shadow-sm p-4 bg-white">
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
