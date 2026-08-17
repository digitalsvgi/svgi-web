import React from 'react';
import { useAuth } from '../context/AuthContext';

export default function Sidebar({ activeTab, setActiveTab }) {
  const { currentUser, logout } = useAuth();
  const role = currentUser?.role || 'college_user';

  // Helper to capitalize first letter of name
  const getUserAvatarLetter = () => {
    if (!currentUser?.name) return 'U';
    return currentUser.name.trim().charAt(0).toUpperCase();
  };

  // Humanize roles
  const formatRoleName = (r) => {
    return r.replace('_', ' ');
  };

  return (
    <aside className="sidebar">
      {/* Brand Header */}
      <div className="sidebar-brand">
        <div className="brand-icon-box">
          <i className="bi bi-shield-check"></i>
        </div>
        <div>
          <div className="brand-text">CWM PORTAL</div>
          <div className="brand-subtext">Work &bull; Track &bull; Verify</div>
        </div>
      </div>

      {/* Navigation List */}
      <nav className="sidebar-nav">
        <div className="nav-label">Core Navigation</div>
        
        {/* Dashboard Link */}
        <button
          onClick={() => setActiveTab('dashboard')}
          className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'dashboard' ? 'active' : ''}`}
          style={{ cursor: 'pointer' }}
        >
          <i className="bi bi-grid-1x2-fill"></i>
          <span>Dashboard</span>
        </button>

        {role === 'super_admin' && (
          <>
            <div className="nav-label mt-2">Institution Control</div>
            <button
              onClick={() => setActiveTab('colleges')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'colleges' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-buildings"></i>
              <span>Colleges</span>
            </button>
            <button
              onClick={() => setActiveTab('departments')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'departments' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-diagram-3-fill"></i>
              <span>Departments</span>
            </button>
            <button
              onClick={() => setActiveTab('users')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'users' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-people-fill"></i>
              <span>Users & Roles</span>
            </button>

            <div className="nav-label mt-2">Work & Analytics</div>
            <button
              onClick={() => setActiveTab('submissions')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'submissions' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-folder-check"></i>
              <span>Submissions</span>
            </button>
            <button
              onClick={() => setActiveTab('reports')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'reports' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-bar-chart-steps"></i>
              <span>Reports & Stats</span>
            </button>

            <div className="nav-label mt-2">System Management</div>
            <button
              onClick={() => setActiveTab('settings')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'settings' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-sliders2-vertical"></i>
              <span>Settings & API</span>
            </button>
            <button
              onClick={() => setActiveTab('logs')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'logs' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-shield-shaded"></i>
              <span>Audit Logs</span>
            </button>
            <button
              onClick={() => setActiveTab('backup')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'backup' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-database-fill-down"></i>
              <span>DB Backups</span>
            </button>
          </>
        )}

        {role === 'admin' && (
          <>
            <div className="nav-label mt-2">Work Operations</div>
            <button
              onClick={() => setActiveTab('submissions')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'submissions' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-folder2-open"></i>
              <span>All Submissions</span>
            </button>
            <button
              onClick={() => setActiveTab('messages')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'messages' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-chat-left-text-fill"></i>
              <span>Live Messages</span>
            </button>
            <button
              onClick={() => setActiveTab('reports')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'reports' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-bar-chart-steps"></i>
              <span>Reports & Analytics</span>
            </button>
          </>
        )}

        {role === 'college_user' && (
          <>
            <div className="nav-label mt-2">My Requests</div>
            <button
              onClick={() => setActiveTab('submissions')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'submissions' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-cloud-arrow-up-fill"></i>
              <span>Work Submissions</span>
            </button>
            <button
              onClick={() => setActiveTab('messages')}
              className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === 'messages' ? 'active' : ''}`}
              style={{ cursor: 'pointer' }}
            >
              <i className="bi bi-chat-left-text-fill"></i>
              <span>Direct Chat</span>
            </button>
          </>
        )}
      </nav>

      {/* User Profile Footer Card */}
      <div className="sidebar-footer">
        <div className="user-profile-widget">
          <div className="user-avatar">
            {getUserAvatarLetter()}
          </div>
          <div className="user-info-text">
            <div className="user-info-name" title={currentUser?.name || 'User'}>
              {currentUser?.name || 'User'}
            </div>
            <div className="user-info-role">
              {formatRoleName(role)}
            </div>
          </div>
          <button 
            onClick={logout} 
            className="text-danger ms-auto bg-transparent border-0 p-0" 
            title="Sign Out"
            style={{ cursor: 'pointer' }}
          >
            <i className="bi bi-power fs-5"></i>
          </button>
        </div>
      </div>
    </aside>
  );
}
