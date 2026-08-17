import React from 'react';
import { useAuth } from '../context/AuthContext';

export default function Header({ pageTitle }) {
  const { currentUser, logout } = useAuth();
  
  // Format current date e.g. Thu, Aug 13, 2026
  const formattedDate = new Date().toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });

  return (
    <header className="d-flex justify-content-between align-items-center px-4 py-3 bg-white border-bottom shadow-sm" style={{ height: '70px' }}>
      {/* Page Icon & Title */}
      <div className="d-flex align-items-center gap-2">
        <div style={{ backgroundColor: 'rgba(78, 184, 73, 0.1)', width: '36px', height: '36px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <i className="bi bi-folder-fill" style={{ color: '#4EB849', fontSize: '1.2rem' }}></i>
        </div>
        <h5 className="fw-bold mb-0 text-dark" style={{ fontSize: '1.15rem' }}>{pageTitle}</h5>
      </div>

      {/* Header Utilities */}
      <div className="d-flex align-items-center gap-3">
        {/* Calendar & Date */}
        <div className="d-flex align-items-center gap-2 text-muted small fw-semibold">
          <i className="bi bi-calendar3 text-primary" style={{ color: '#0C4DA2' }}></i>
          <span>{formattedDate}</span>
        </div>

        <div style={{ width: '1px', height: '20px', backgroundColor: '#e2e8f0' }}></div>

        {/* User Profile Info */}
        <div className="d-flex align-items-center gap-2 text-muted small fw-semibold">
          <i className="bi bi-person-circle text-primary" style={{ color: '#0C4DA2', fontSize: '1.1rem' }}></i>
          <span>{currentUser?.name || 'User'}</span>
        </div>

        <div style={{ width: '1px', height: '20px', backgroundColor: '#e2e8f0' }}></div>

        {/* Red Logout Button */}
        <button 
          onClick={logout}
          className="btn btn-outline-danger btn-sm d-flex align-items-center gap-1.5 px-3 py-1.5 fw-bold"
          style={{ borderRadius: '8px', fontSize: '0.8rem' }}
        >
          <i className="bi bi-box-arrow-right"></i>
          <span>Logout</span>
        </button>
      </div>
    </header>
  );
}
