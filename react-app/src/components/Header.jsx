import React from 'react';
import { useAuth } from '../context/AuthContext';

export default function Header({ pageTitle }) {
  const { currentUser } = useAuth();
  
  // Format current date e.g. 17 Aug 2026
  const options = { day: '2-digit', month: 'short', year: 'numeric' };
  const formattedDate = new Date().toLocaleDateString('en-GB', options).replace(/ /g, ' ');

  const roleLabels = {
    super_admin: 'Super Admin',
    admin: 'Central Admin',
    college_user: 'College User'
  };

  return (
    <header className="glass-header d-flex justify-content-between align-items-center px-4 py-3 border-bottom bg-white">
      <div>
        <h4 className="fw-bold mb-0 text-dark" style={{ color: '#4EB849' }}>{pageTitle}</h4>
        <small className="text-muted">{formattedDate}</small>
      </div>

      <div className="d-flex align-items-center gap-3">
        <div className="text-end">
          <div className="fw-bold text-dark mb-0">{currentUser?.name}</div>
          <span className="badge rounded-pill text-white px-2 py-1" style={{ backgroundColor: '#4EB849', fontSize: '0.75rem' }}>
            {roleLabels[currentUser?.role] || 'Member'}
          </span>
        </div>
        
        {/* Profile Avatar */}
        <div 
          className="rounded-circle border d-flex align-items-center justify-content-center fw-bold text-white bg-primary shadow-sm"
          style={{ width: '40px', height: '40px', fontSize: '1.1rem', backgroundColor: '#0C4DA2' }}
        >
          {currentUser?.name ? currentUser.name.charAt(0).toUpperCase() : 'U'}
        </div>
      </div>
    </header>
  );
}
