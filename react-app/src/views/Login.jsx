import React, { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { db } from '../firebase/firebaseConfig';
import { collection, getDocs, doc, getDoc } from 'firebase/firestore';

export default function Login() {
  const { login, logout } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  
  // Custom design states (Central Admin vs College Login)
  const [loginMode, setLoginMode] = useState('admin'); // 'admin' or 'college'
  const [collegesList, setCollegesList] = useState([]);
  const [selectedCollegeId, setSelectedCollegeId] = useState('');

  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  // Load active colleges list from Firestore to populate the dropdown select
  useEffect(() => {
    const loadColleges = async () => {
      try {
        const colSnap = await getDocs(collection(db, 'colleges'));
        const list = [];
        colSnap.forEach(doc => {
          list.push({ id: doc.id, ...doc.data() });
        });
        setCollegesList(list);
      } catch (err) {
        console.error("Failed to load colleges for dropdown:", err);
      }
    };
    loadColleges();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email || !password) {
      return setError('Please enter your email and password.');
    }
    if (loginMode === 'college' && !selectedCollegeId) {
      return setError('Please select your institution / college.');
    }

    try {
      setError('');
      setLoading(true);

      // 1. Authenticate with Firebase Authentication
      const authResult = await login(email, password);
      
      // 2. Fetch the user's role profile from Firestore /users/{uid}
      const userUid = authResult.user.uid;
      const userDocRef = doc(db, 'users', userUid);
      const userSnapshot = await getDoc(userDocRef);
      
      if (!userSnapshot.exists()) {
        await logout();
        return setError('Your user profile could not be verified in the database.');
      }

      const userData = userSnapshot.data();

      // 3. Validation: Verify that login mode matches the user's profile role
      if (loginMode === 'admin') {
        if (userData.role !== 'super_admin' && userData.role !== 'admin') {
          await logout();
          return setError('Invalid credentials for Central Admin Login.');
        }
      } else if (loginMode === 'college') {
        if (userData.role !== 'college_user') {
          await logout();
          return setError('Invalid credentials for College Login.');
        }
        if (userData.collegeId !== selectedCollegeId) {
          await logout();
          return setError('Incorrect college selection for this user credentials.');
        }
      }

    } catch (err) {
      console.error(err);
      setError('Invalid email, password, or role credentials.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container-fluid p-0 overflow-hidden" style={{ minHeight: '100vh', background: '#0C4DA2' }}>
      <div className="row g-0" style={{ minHeight: '100vh' }}>
        
        {/* Left Side: Mockup Branding Panel */}
        <div className="col-lg-6 d-none d-lg-flex flex-column justify-content-between p-5 position-relative split-left-panel" style={{
          backgroundImage: 'linear-gradient(145deg, rgba(12, 77, 162, 0.92) 0%, rgba(5, 33, 71, 0.96) 100%), url("https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1200&q=80")',
          backgroundSize: 'cover',
          backgroundPosition: 'center',
          color: '#ffffff',
          zIndex: 1,
          overflow: 'hidden'
        }}>
          {/* Grid Dots Overlay Decoration */}
          <div style={{ position: 'absolute', top: '3.5rem', right: '2.5rem', opacity: 0.12, display: 'flex', gap: '6px', flexDirection: 'column', zIndex: 1 }}>
            <div className="d-flex gap-1.5"><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span></div>
            <div className="d-flex gap-1.5"><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span></div>
            <div className="d-flex gap-1.5"><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span><span className="fw-bold">•</span></div>
          </div>

          <div style={{ zIndex: 10, position: 'relative', display: 'flex', flexDirection: 'column', height: '100%' }}>
            {/* Top Institution Banner Logo */}
            <div className="brand-banner-card" style={{ padding: '0.55rem 1.25rem', backgroundColor: '#ffffff', borderRadius: '12px', marginBottom: '2.5rem', display: 'inline-block', width: 'fit-content' }}>
              <img src="/assets/img/logo.png" alt="Sri Venkateshwaraa" style={{ maxHeight: '52px', width: 'auto', display: 'block' }} />
            </div>

            <div className="left-panel-category" style={{ color: '#4EB849', fontWeight: 700, letterSpacing: '0.08em', fontSize: '0.72rem', marginBottom: '0.5rem' }}>
              DOCUMENT & WORK MANAGEMENT SYSTEM
            </div>
            <h1 className="left-panel-title" style={{ fontSize: '2.2rem', fontWeight: 800, lineHeight: 1.25, marginBottom: '1.2rem', color: '#ffffff' }}>
              Secure. Centralized.<br />
              <span style={{ color: '#4EB849' }}>Organized.</span><br />
              All in One Place.
            </h1>
            <p className="left-panel-desc" style={{ color: 'rgba(255, 255, 255, 0.75)', fontSize: '0.85rem', lineHeight: 1.6, marginBottom: '2.5rem', maxWidth: '440px' }}>
              Upload, organize, and manage institutional work requests, tracking workflows, updates, and real-time status with role-based access.
            </p>

            {/* Service Cards */}
            <div className="d-flex gap-3 mb-4 flex-wrap">
              <div className="text-center" style={{ width: '76px' }}>
                <div className="service-card-box" style={{ backgroundColor: '#ffffff', borderRadius: '12px', width: '50px', height: '50px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 0.5rem', boxShadow: '0 4px 12px rgba(0,0,0,0.12)' }}>
                  <i className="bi bi-shield-check" style={{ color: '#4EB849', fontSize: '1.4rem' }}></i>
                </div>
                <span style={{ color: '#ffffff', fontSize: '0.7rem', fontWeight: 600, display: 'block', lineHeight: 1.25 }}>Secure<br />Access</span>
              </div>
              <div className="text-center" style={{ width: '76px' }}>
                <div className="service-card-box" style={{ backgroundColor: '#ffffff', borderRadius: '12px', width: '50px', height: '50px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 0.5rem', boxShadow: '0 4px 12px rgba(0,0,0,0.12)' }}>
                  <i className="bi bi-folder-fill" style={{ color: '#0C4DA2', fontSize: '1.4rem' }}></i>
                </div>
                <span style={{ color: '#ffffff', fontSize: '0.7rem', fontWeight: 600, display: 'block', lineHeight: 1.25 }}>Centralized<br />Storage</span>
              </div>
              <div className="text-center" style={{ width: '76px' }}>
                <div className="service-card-box" style={{ backgroundColor: '#ffffff', borderRadius: '12px', width: '50px', height: '50px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 0.5rem', boxShadow: '0 4px 12px rgba(0,0,0,0.12)' }}>
                  <i className="bi bi-graph-up-arrow" style={{ color: '#4EB849', fontSize: '1.2rem' }}></i>
                </div>
                <span style={{ color: '#ffffff', fontSize: '0.7rem', fontWeight: 600, display: 'block', lineHeight: 1.25 }}>Status<br />Tracking</span>
              </div>
              <div className="text-center" style={{ width: '76px' }}>
                <div className="service-card-box" style={{ backgroundColor: '#ffffff', borderRadius: '12px', width: '50px', height: '50px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 0.5rem', boxShadow: '0 4px 12px rgba(0,0,0,0.12)' }}>
                  <i className="bi bi-chat-dots-fill" style={{ color: '#0C4DA2', fontSize: '1.3rem' }}></i>
                </div>
                <span style={{ color: '#ffffff', fontSize: '0.7rem', fontWeight: 600, display: 'block', lineHeight: 1.25 }}>Live<br />Support</span>
              </div>
            </div>

            {/* Unified Metrics Box */}
            <div style={{ backgroundColor: '#ffffff', borderRadius: '14px', padding: '0.95rem 1.4rem', display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '2rem', boxShadow: '0 4px 15px rgba(0,0,0,0.1)', maxWidth: '360px' }}>
              <div className="d-flex align-items-center gap-3 flex-fill justify-content-center">
                <div style={{ backgroundColor: 'rgba(78, 184, 73, 0.1)', width: '36px', height: '36px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <i className="bi bi-building" style={{ color: '#4EB849', fontSize: '1.05rem' }}></i>
                </div>
                <div className="text-start">
                  <div style={{ fontSize: '1.3rem', fontWeight: 800, color: '#0C4DA2', lineHeight: 1, marginBottom: '2px' }}>{collegesList.length || 8}+</div>
                  <div style={{ fontSize: '0.65rem', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Institutions</div>
                </div>
              </div>
              <div style={{ width: '1px', height: '30px', backgroundColor: '#e2e8f0', margin: '0 1rem' }}></div>
              <div className="d-flex align-items-center gap-3 flex-fill justify-content-center">
                <div style={{ backgroundColor: 'rgba(12, 77, 162, 0.1)', width: '36px', height: '36px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <i className="bi bi-file-earmark-text" style={{ color: '#0C4DA2', fontSize: '1.05rem' }}></i>
                </div>
                <div className="text-start">
                  <div style={{ fontSize: '1.3rem', fontWeight: 800, color: '#0C4DA2', lineHeight: 1, marginBottom: '2px' }}>&infin;</div>
                  <div style={{ fontSize: '0.65rem', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Work Records</div>
                </div>
              </div>
            </div>

            {/* Green Wave SVG Background Decoration */}
            <div style={{ position: 'absolute', bottom: '-48px', left: '-48px', width: 'calc(100% + 96px)', height: '220px', overflow: 'hidden', zIndex: -1, pointerEvents: 'none' }}>
              <svg viewBox="0 0 500 150" preserveAspectRatio="none" style={{ height: '100%', width: '100%' }}>
                <path d="M-10.00,85.00 C150.00,165.00 320.00,20.00 510.00,105.00 L500.00,150.00 L0.00,150.00 Z" style={{ stroke: 'none', fill: '#4EB849', fillOpacity: 0.2 }}></path>
              </svg>
            </div>

            {/* Developer Credits Note */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', zIndex: 10, position: 'relative', marginTop: 'auto' }}>
              <div style={{ backgroundColor: 'rgba(255,255,255,0.18)', border: '1.5px solid #ffffff', width: '28px', height: '28px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                <i className="bi bi-shield-fill-check" style={{ color: '#ffffff', fontSize: '0.95rem' }}></i>
              </div>
              <div className="text-start developer-credit-text" style={{ fontSize: '0.68rem', color: '#ffffff', lineHeight: 1.4, fontWeight: 500 }}>
                Developed by Central Management & Digital Marketing &ndash; Sri Venkateshwaraa Group of Institutions, Puducherry.
              </div>
            </div>
          </div>
        </div>

        {/* Right Side: Curved Login Panel */}
        <div className="col-12 col-lg-6 d-flex align-items-center justify-content-center bg-white p-5 position-relative shadow-lg" style={{
          borderTopLeftRadius: '60px',
          borderBottomLeftRadius: '60px',
          marginLeft: '-50px',
          paddingLeft: '5.5rem',
          zIndex: 10
        }}>
          
          <div className="w-100" style={{ maxWidth: '440px' }}>
            <div className="mb-4">
              <h2 className="fw-bold text-dark mb-2">Welcome <span style={{ color: '#4EB849' }}>Back!</span> 👋</h2>
              <p className="text-muted">Sign in to continue to your dashboard</p>
            </div>

            {error && (
              <div className="alert alert-danger d-flex align-items-center gap-2 border-0 rounded-4 mb-3" style={{ background: 'rgba(239, 68, 68, 0.08)', color: '#ef4444' }}>
                <i className="bi bi-exclamation-triangle-fill"></i>
                <div className="small fw-semibold">{error}</div>
              </div>
            )}

            {/* Role / Scope Switcher Pills */}
            <div className="form-role-pills mb-4 d-flex justify-content-between">
              <button 
                type="button" 
                className={`form-role-pill-btn btn border-0 flex-fill text-center d-flex align-items-center justify-content-center gap-2 ${loginMode === 'admin' ? 'active' : ''}`} 
                onClick={() => {
                  setLoginMode('admin');
                  setError('');
                  setEmail('');
                  setPassword('');
                }}
                style={{ padding: '0.7rem 1rem' }}
              >
                <i className="bi bi-bank"></i> Central Admin
              </button>
              <button 
                type="button" 
                className={`form-role-pill-btn btn border-0 flex-fill text-center d-flex align-items-center justify-content-center gap-2 ${loginMode === 'college' ? 'active' : ''}`} 
                onClick={() => {
                  setLoginMode('college');
                  setError('');
                  setEmail('');
                  setPassword('');
                }}
                style={{ padding: '0.7rem 1rem' }}
              >
                <i className="bi bi-mortarboard"></i> College Login
              </button>
            </div>

            <form onSubmit={handleSubmit}>
              
              {/* Dynamic Institution Selection Dropdown (Only for College Login) */}
              {loginMode === 'college' && (
                <div className="mb-3">
                  <label className="form-label small fw-bold text-muted" style={{ marginBottom: '0.45rem' }}>Select Institution / College</label>
                  <select 
                    id="college_id_select" 
                    className="form-select"
                    value={selectedCollegeId}
                    onChange={(e) => setSelectedCollegeId(e.target.value)}
                    style={{ height: '52px', borderRadius: '12px', backgroundColor: '#f8fafc', border: '1.5px solid #e2e8f0', fontWeight: '500', fontSize: '0.925rem' }}
                    required
                  >
                    <option value="">&mdash; Choose Institution &mdash;</option>
                    {collegesList.map((col) => (
                      <option key={col.id} value={col.id}>
                        {col.name} ({col.code})
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Institutional Email Address */}
              <div className="mb-3">
                <label className="form-label small fw-bold text-muted">Email Address</label>
                <div className="input-icon-wrapper">
                  <span className="input-icon-left"><i className="bi bi-envelope"></i></span>
                  <input
                    type="email"
                    id="email"
                    className="form-control"
                    placeholder={loginMode === 'admin' ? 'admin' : 'you@institution.edu'}
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    autoFocus
                  />
                </div>
              </div>

              {/* Password */}
              <div className="mb-4">
                <label className="form-label small fw-bold text-muted">Password</label>
                <div className="input-icon-wrapper">
                  <span className="input-icon-left"><i className="bi bi-lock"></i></span>
                  <input
                    type={showPassword ? 'text' : 'password'}
                    id="password"
                    className="form-control"
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                  />
                  <button 
                    type="button" 
                    className="password-toggle-icon" 
                    onClick={() => setShowPassword(!showPassword)}
                    title="Toggle password visibility" 
                    style={{ position: 'absolute', right: '1.15rem', color: '#94a3b8', cursor: 'pointer', border: 'none', background: 'transparent', padding: '0' }}
                  >
                    <i className={`bi ${showPassword ? 'bi-eye' : 'bi-eye-slash'}`} style={{ fontSize: '1.1rem' }}></i>
                  </button>
                </div>
              </div>

              {/* Remember Me & Forgot Password */}
              <div className="d-flex justify-content-between align-items-center mb-4">
                <div className="form-check m-0 d-flex align-items-center gap-2">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id="rememberCheck"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                    style={{ cursor: 'pointer', width: '17px', height: '17px', borderColor: '#cbd5e1', borderRadius: '4px' }}
                  />
                  <label className="form-check-label small fw-semibold text-muted" htmlFor="rememberCheck" style={{ cursor: 'pointer' }}>
                    Remember Me
                  </label>
                </div>
                <a href="#forgot" className="small fw-bold text-decoration-none" style={{ color: '#0C4DA2' }}>
                  Forgot Password?
                </a>
              </div>

              {/* Submit Button */}
              <button
                type="submit"
                disabled={loading}
                className="btn btn-primary w-100 fw-bold d-flex justify-content-between align-items-center px-4 py-3 btn-submit-signin"
                style={{
                  height: '52px',
                  borderRadius: '12px',
                  backgroundColor: '#0C4DA2',
                  borderColor: '#0C4DA2',
                  boxShadow: '0 4px 15px rgba(12, 77, 162, 0.2)'
                }}
              >
                <span>{loading ? 'Authenticating...' : 'Sign In'}</span>
                <i className="bi bi-arrow-right-circle-fill fs-5"></i>
              </button>
            </form>

            {/* Secure Access Note */}
            <div className="text-center my-4 position-relative">
              <div style={{ position: 'absolute', top: '50%', left: 0, right: 0, height: '1px', background: '#e2e8f0', zIndex: 1 }}></div>
              <span style={{ position: 'relative', background: '#ffffff', padding: '0 1rem', color: '#94a3b8', fontSize: '0.72rem', fontWeight: 700, letterSpacing: '0.05em', textTransform: 'uppercase', zIndex: 2 }}>
                Secure & Trusted Access
              </span>
            </div>
            <div className="d-flex align-items-center justify-content-center gap-2 text-muted">
              <i className="bi bi-shield-check text-success" style={{ fontSize: '1.15rem' }}></i>
              <span style={{ fontSize: '0.72rem', fontWeight: 600, color: '#64748b' }}>
                Your data is protected with enterprise-grade security
              </span>
            </div>
          </div>

        </div>

      </div>
    </div>
  );
}
