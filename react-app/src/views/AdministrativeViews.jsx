import React, { useEffect, useState } from 'react';
import { db } from '../firebase/firebaseConfig';
import { collection, onSnapshot, getDocs } from 'firebase/firestore';

// 1. COLLEGES VIEW COMPONENT
export function CollegesView() {
  const [colleges, setColleges] = useState([]);

  useEffect(() => {
    const unsub = onSnapshot(collection(db, 'colleges'), (snap) => {
      const list = [];
      snap.forEach(d => list.push({ id: d.id, ...d.data() }));
      setColleges(list);
    });
    return () => unsub();
  }, []);

  return (
    <div className="container-fluid py-4 bg-white rounded-4 shadow-sm">
      <h5 className="fw-bold mb-1" style={{ color: '#4EB849' }}>Institutional Colleges</h5>
      <p className="text-muted small mb-4">View and audit all registered campus colleges and institutions.</p>
      
      <div className="row g-4">
        {colleges.map((c) => (
          <div key={c.id} className="col-12 col-md-6 col-lg-4">
            <div className="card border rounded-4 shadow-xs p-4 bg-light d-flex flex-column justify-content-between h-100">
              <div>
                <div className="d-flex align-items-center justify-content-between mb-3">
                  <span className="badge rounded-pill bg-primary px-3 py-1 text-white" style={{ backgroundColor: '#0C4DA2' }}>{c.code}</span>
                  <span className="small fw-semibold text-success">&bull; Active</span>
                </div>
                <h6 className="fw-bold text-dark mb-2">{c.name}</h6>
                <p className="text-muted small mb-0"><i className="bi bi-envelope me-2"></i>{c.email}</p>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// 2. DEPARTMENTS VIEW COMPONENT
export function DepartmentsView() {
  const [departments, setDepartments] = useState([]);
  const [colleges, setColleges] = useState({});

  useEffect(() => {
    const unsubDept = onSnapshot(collection(db, 'departments'), (snap) => {
      const list = [];
      snap.forEach(d => list.push({ id: d.id, ...d.data() }));
      setDepartments(list);
    });

    const loadColleges = async () => {
      const colSnap = await getDocs(collection(db, 'colleges'));
      const colList = {};
      colSnap.forEach(d => { colList[d.id] = d.data().name; });
      setColleges(colList);
    };
    loadColleges();

    return () => unsubDept();
  }, []);

  return (
    <div className="container-fluid py-4 bg-white rounded-4 shadow-sm">
      <h5 className="fw-bold mb-1" style={{ color: '#4EB849' }}>Departments Management</h5>
      <p className="text-muted small mb-4">Core department structures mapped under each institutional college.</p>

      <div className="table-responsive">
        <table className="table table-hover align-middle">
          <thead className="table-light">
            <tr>
              <th>ID</th>
              <th>Department Name</th>
              <th>Parent Institution / College</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {departments.map((d, index) => (
              <tr key={d.id}>
                <td>{index + 1}</td>
                <td className="fw-bold text-dark">{d.name}</td>
                <td className="small text-muted">{colleges[d.collegeId] || d.collegeId}</td>
                <td>
                  <span className="badge rounded-pill bg-success bg-opacity-10 text-success px-2.5 py-1 small fw-semibold" style={{ color: '#4EB849' }}>
                    &bull; Active
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// 3. USER DIRECTORY VIEW COMPONENT
export function UsersView() {
  const [users, setUsers] = useState([]);
  const [colleges, setColleges] = useState({});

  useEffect(() => {
    const unsubUsers = onSnapshot(collection(db, 'users'), (snap) => {
      const list = [];
      snap.forEach(d => list.push({ id: d.id, ...d.data() }));
      setUsers(list);
    });

    const loadColleges = async () => {
      const colSnap = await getDocs(collection(db, 'colleges'));
      const colList = {};
      colSnap.forEach(d => { colList[d.id] = d.data().name; });
      setColleges(colList);
    };
    loadColleges();

    return () => unsubUsers();
  }, []);

  return (
    <div className="container-fluid py-4 bg-white rounded-4 shadow-sm">
      <h5 className="fw-bold mb-1" style={{ color: '#4EB849' }}>User Directory</h5>
      <p className="text-muted small mb-4">View authorized users, roles, and institutional affiliations.</p>

      <div className="table-responsive">
        <table className="table table-hover align-middle m-0">
          <thead className="table-light" style={{ borderBottom: '2.5px solid #4EB849' }}>
            <tr style={{ fontSize: '0.78rem', textTransform: 'uppercase', color: '#475569' }}>
              <th>ID</th>
              <th>User Profile</th>
              <th>Email Address</th>
              <th>System Role</th>
              <th>Institutional Scope</th>
              <th>Account Status</th>
              <th className="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            {users.map((u, index) => (
              <tr key={u.id}>
                <td className="text-muted small">{index + 1}</td>
                <td>
                  <div className="d-flex align-items-center gap-3">
                    <div className="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                         style={{ 
                           width: '38px', 
                           height: '38px', 
                           backgroundColor: u.role === 'super_admin' ? '#0C4DA2' : (u.role === 'admin' ? '#4EB849' : '#8b5cf6'), 
                           fontSize: '0.95rem' 
                         }}>
                      {u.name ? u.name.trim().charAt(0).toUpperCase() : 'U'}
                    </div>
                    <div className="fw-bold text-dark">{u.name}</div>
                  </div>
                </td>
                <td style={{ fontSize: '0.9rem', color: '#475569' }}>{u.email}</td>
                <td>
                  <span className="badge bg-secondary bg-opacity-10 text-secondary px-2.5 py-1.5 small fw-bold" style={{ fontSize: '0.7rem' }}>
                    {u.role ? u.role.replace('_', ' ').toUpperCase() : 'MEMBER'}
                  </span>
                </td>
                <td>
                  <div className="d-flex align-items-center gap-2 text-muted small">
                    {u.collegeId ? (
                      <>
                        <i className="bi bi-bank text-secondary"></i>
                        <span>{colleges[u.collegeId] || u.collegeId}</span>
                      </>
                    ) : (
                      <>
                        <i className="bi bi-shield-check text-primary"></i>
                        <span>System (Central)</span>
                      </>
                    )}
                  </div>
                </td>
                <td>
                  <span className="badge bg-success bg-opacity-10 text-success px-2.5 py-1.5 rounded-pill small fw-semibold" style={{ fontSize: '0.78rem' }}>
                    &bull; Active
                  </span>
                </td>
                <td className="text-end">
                  <div className="d-flex gap-2 justify-content-end">
                    <button className="btn btn-sm btn-outline-primary border-0 bg-transparent p-1" title="Edit User">
                      <i className="bi bi-pencil-square" style={{ fontSize: '1rem', color: '#0C4DA2' }}></i>
                    </button>
                    <button className="btn btn-sm text-muted border-0 bg-transparent p-1" title="Actions">
                      <i className="bi bi-three-dots" style={{ fontSize: '1.1rem' }}></i>
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// 4. ACTIVITY LOGS VIEW COMPONENT
export function LogsView() {
  const [logs, setLogs] = useState([]);

  useEffect(() => {
    const unsubLogs = onSnapshot(collection(db, 'activity_logs'), (snap) => {
      const list = [];
      snap.forEach(d => list.push({ id: d.id, ...d.data() }));
      setLogs(list);
    });
    return () => unsubLogs();
  }, []);

  return (
    <div className="container-fluid py-4 bg-white rounded-4 shadow-sm">
      <h5 className="fw-bold mb-1" style={{ color: '#4EB849' }}>Activity Audits</h5>
      <p className="text-muted small mb-4">Timeline database logs mapping administrative actions and record updates.</p>

      <div className="table-responsive">
        <table className="table table-hover align-middle">
          <thead className="table-light">
            <tr>
              <th>Operator Name</th>
              <th>Performed Action</th>
              <th>Date & Time</th>
            </tr>
          </thead>
          <tbody>
            {logs.length === 0 ? (
              <tr>
                <td colSpan="3" className="text-center text-muted py-4 small">No audit actions recorded yet.</td>
              </tr>
            ) : (
              logs.map((l) => (
                <tr key={l.id}>
                  <td className="fw-semibold text-dark">{l.userName}</td>
                  <td>{l.action}</td>
                  <td className="small text-muted">
                    {l.createdAt ? new Date(l.createdAt.seconds * 1000).toLocaleString('en-GB') : ''}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
