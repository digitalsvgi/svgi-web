import React, { useState } from 'react';
import { useAuth } from '../context/AuthContext';

export default function Login() {
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(false);
  
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email || !password) {
      return setError('Please fill in all fields.');
    }

    try {
      setError('');
      setLoading(true);
      await login(email, password);
    } catch (err) {
      console.error(err);
      setError('Invalid email or password.');
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
                  <div style={{ fontSize: '1.3rem', fontWeight: 800, color: '#0C4DA2', lineHeight: 1, marginBottom: '2px' }}>8+</div>
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
              <div className="text-start" style={{ fontSize: '0.68rem', color: '#ffffff', lineHeight: 1.4, fontWeight: 500 }}>
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
              <h2 className="fw-bold text-dark mb-2">Welcome Back</h2>
              <p className="text-muted">Enter your institutional credentials to access the CWM portal.</p>
            </div>

            {error && (
              <div className="alert alert-danger d-flex align-items-center gap-2 border-0 rounded-4" style={{ background: 'rgba(239, 68, 68, 0.08)', color: '#ef4444' }}>
                <i className="bi bi-exclamation-triangle-fill"></i>
                <div className="small fw-semibold">{error}</div>
              </div>
            )}

            <form onSubmit={handleSubmit}>
              {/* Institutional Email Address */}
              <div className="mb-3">
                <label className="form-label small fw-bold text-muted">Institutional Email Address</label>
                <div className="input-group">
                  <span className="input-group-text bg-light border-0" style={{ borderTopLeftRadius: '12px', borderBottomLeftRadius: '12px' }}>
                    <i className="bi bi-envelope text-muted"></i>
                  </span>
                  <input
                    type="email"
                    className="form-control bg-light border-0"
                    placeholder="you@srivenkateshwaraa.edu.in"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    style={{ borderTopRightRadius: '12px', borderBottomRightRadius: '12px', padding: '0.75rem 1rem' }}
                    required
                  />
                </div>
              </div>

              {/* Password */}
              <div className="mb-4">
                <label className="form-label small fw-bold text-muted">Secure Password</label>
                <div className="input-group">
                  <span className="input-group-text bg-light border-0" style={{ borderTopLeftRadius: '12px', borderBottomLeftRadius: '12px' }}>
                    <i className="bi bi-lock text-muted"></i>
                  </span>
                  <input
                    type="password"
                    className="form-control bg-light border-0"
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    style={{ borderTopRightRadius: '12px', borderBottomRightRadius: '12px', padding: '0.75rem 1rem' }}
                    required
                  />
                </div>
              </div>

              {/* Remember Me & Forgot Password */}
              <div className="d-flex justify-content-between align-items-center mb-4">
                <div className="form-check">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id="rememberCheck"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                    style={{ cursor: 'pointer' }}
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
                className="btn btn-primary w-100 fw-bold d-flex justify-content-between align-items-center px-4 py-3"
                style={{
                  borderRadius: '12px',
                  backgroundColor: '#0C4DA2',
                  borderColor: '#0C4DA2',
                  boxShadow: '0 4px 15px rgba(12, 77, 162, 0.35)'
                }}
              >
                <span>{loading ? 'Authenticating...' : 'Sign In'}</span>
                <i className="bi bi-arrow-right-circle-fill fs-5"></i>
              </button>
            </form>

            <div className="mt-5 text-center">
              <span className="text-muted small">Need helper credentials or setup? </span>
              <a href="mailto:digital@srivenkateshwaraa.edu.in" className="small fw-bold text-decoration-none" style={{ color: '#4EB849' }}>
                Contact IT Support
              </a>
            </div>
          </div>

        </div>

      </div>
    </div>
  );
}
