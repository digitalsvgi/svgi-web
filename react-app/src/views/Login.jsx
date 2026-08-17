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
        <div className="col-lg-6 d-none d-lg-flex flex-column justify-content-between p-5 position-relative" style={{
          backgroundImage: 'linear-gradient(145deg, rgba(12, 77, 162, 0.9) 0%, rgba(5, 33, 71, 0.95) 100%), url("https://images.unsplash.com/photo-1562774053-701939374585?auto=format&fit=crop&w=1200&q=80")',
          backgroundSize: 'cover',
          backgroundPosition: 'center',
          color: '#ffffff',
          zIndex: 1
        }}>
          {/* Logo Round Banner Card */}
          <div className="d-flex align-items-center mb-4">
            <div className="bg-white rounded-4 p-3 shadow-lg d-flex align-items-center" style={{ width: '100%', maxWidth: '340px' }}>
              <img src="/assets/img/logo.png" alt="SVGI Logo" style={{ height: '50px', objectFit: 'contain' }} />
            </div>
          </div>

          {/* Tagline */}
          <div className="my-auto">
            <h1 className="fw-extrabold display-5 tracking-tight mb-3" style={{ lineHeight: '1.2' }}>
              Secure. Centralized. <br />
              <span style={{ color: '#4EB849' }}>Organized.</span> <br />
              All in One Place.
            </h1>
            <p className="text-white-50 lead mb-5">
              The official centralized submission and work monitoring dashboard of Sri Venkateshwaraa Group of Institutions.
            </p>

            {/* Service Grid Cards */}
            <div className="row g-3 mb-5">
              <div className="col-6">
                <div className="bg-white bg-opacity-10 border border-white border-opacity-10 rounded-4 p-3 d-flex flex-column align-items-center text-center shadow-sm">
                  <i className="bi bi-shield-lock-fill text-white fs-4 mb-2"></i>
                  <span className="small fw-semibold text-white-50">Secure Access</span>
                </div>
              </div>
              <div className="col-6">
                <div className="bg-white bg-opacity-10 border border-white border-opacity-10 rounded-4 p-3 d-flex flex-column align-items-center text-center shadow-sm">
                  <i className="bi bi-hdd-network-fill text-white fs-4 mb-2"></i>
                  <span className="small fw-semibold text-white-50">Centralized Storage</span>
                </div>
              </div>
              <div className="col-6">
                <div className="bg-white bg-opacity-10 border border-white border-opacity-10 rounded-4 p-3 d-flex flex-column align-items-center text-center shadow-sm">
                  <i className="bi bi-calendar-event-fill text-white fs-4 mb-2"></i>
                  <span className="small fw-semibold text-white-50">Status Tracking</span>
                </div>
              </div>
              <div className="col-6">
                <div className="bg-white bg-opacity-10 border border-white border-opacity-10 rounded-4 p-3 d-flex flex-column align-items-center text-center shadow-sm">
                  <i className="bi bi-telephone-outbound-fill text-white fs-4 mb-2"></i>
                  <span className="small fw-semibold text-white-50">Live Support</span>
                </div>
              </div>
            </div>

            {/* Metrics Separator Box */}
            <div className="bg-white bg-opacity-5 border border-white border-opacity-10 rounded-4 p-4 d-flex justify-content-around align-items-center shadow-sm" style={{ backdropFilter: 'blur(10px)' }}>
              <div className="text-center">
                <div className="h3 fw-bold mb-0 text-white">8</div>
                <div className="small text-white-50">Institutions</div>
              </div>
              <div style={{ width: '1px', height: '40px', background: 'rgba(255, 255, 255, 0.2)' }}></div>
              <div className="text-center">
                <div className="h3 fw-bold mb-0 text-white">100%</div>
                <div className="small text-white-50">Work Records</div>
              </div>
            </div>
          </div>

          {/* Wavy SVG Background & Developer Credits */}
          <div className="position-absolute bottom-0 start-0 end-0 overflow-hidden" style={{ height: '90px', zIndex: -1 }}>
            <svg viewBox="0 0 500 150" preserveAspectRatio="none" style={{ height: '100%', width: '100%' }}>
              <path d="M-5.36,94.24 C149.99,150.00 271.49,-49.98 506.20,124.83 L500.00,150.00 L0.00,150.00 Z" style={{ stroke: 'none', fill: '#4EB849', fillOpacity: 0.15 }}></path>
            </svg>
            <div className="position-absolute bottom-3 start-5 text-white-50 small" style={{ zIndex: 10, left: '30px', bottom: '20px' }}>
              Sri Venkateshwaraa Group of Institutions &bull; Puducherry
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
