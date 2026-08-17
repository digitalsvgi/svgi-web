import React, { useState } from 'react';
import { AuthProvider, useAuth } from './context/AuthContext';
import Login from './views/Login';
import Sidebar from './components/Sidebar';
import Header from './components/Header';
import DashboardView from './views/DashboardView';
import SubmissionsView from './views/SubmissionsView';
import { 
  CollegesView, 
  DepartmentsView, 
  UsersView, 
  LogsView 
} from './views/AdministrativeViews';

function AppContent() {
  const { currentUser, loading } = useAuth();
  const [activeTab, setActiveTab] = useState('dashboard');

  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center" style={{ minHeight: '100vh', background: '#f8fafc' }}>
        <div className="spinner-border text-primary" role="status" style={{ color: '#0C4DA2' }}>
          <span className="visually-hidden">Loading...</span>
        </div>
      </div>
    );
  }

  // Redirect to login if user is not authenticated
  if (!currentUser) {
    return <Login />;
  }

  // Map active tabs to their respective view components
  const renderView = () => {
    switch (activeTab) {
      case 'dashboard':
        return <DashboardView />;
      case 'colleges':
        return <CollegesView />;
      case 'departments':
        return <DepartmentsView />;
      case 'submissions':
        return <SubmissionsView />;
      case 'users':
        return <UsersView />;
      case 'logs':
        return <LogsView />;
      default:
        return <DashboardView />;
    }
  };

  const getPageTitle = () => {
    switch (activeTab) {
      case 'dashboard': return 'Operations Overview';
      case 'colleges': return 'Institutional Colleges';
      case 'departments': return 'Departments Management';
      case 'submissions': return 'Submissions Desk';
      case 'users': return 'User Directory';
      case 'logs': return 'Activity Audits';
      default: return 'CWM Portal';
    }
  };

  return (
    <div className="app-wrapper">
      {/* Sidebar Navigation */}
      <Sidebar activeTab={activeTab} setActiveTab={setActiveTab} />
      
      {/* Main Content Body */}
      <div className="main-content flex-grow-1">
        <Header pageTitle={getPageTitle()} />
        <div className="p-4">
          {renderView()}
        </div>
      </div>
    </div>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <AppContent />
    </AuthProvider>
  );
}
