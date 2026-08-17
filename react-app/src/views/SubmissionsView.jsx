import React, { useEffect, useState, useRef } from 'react';
import { db } from '../firebase/firebaseConfig';
import { 
  collection, 
  addDoc, 
  updateDoc, 
  doc, 
  onSnapshot, 
  query, 
  where, 
  orderBy, 
  getDocs,
  Timestamp 
} from 'firebase/firestore';
import { useAuth } from '../context/AuthContext';

export default function SubmissionsView() {
  const { currentUser } = useAuth();
  const [submissions, setSubmissions] = useState([]);
  const [colleges, setColleges] = useState([]);
  const [departments, setDepartments] = useState([]);
  
  // Modals & Forms States
  const [selectedSub, setSelectedSub] = useState(null);
  const [showAddModal, setShowAddModal] = useState(false);
  const [showDetailsModal, setShowDetailsModal] = useState(false);
  
  // Add Form Inputs
  const [newTitle, setNewTitle] = useState('');
  const [newDescription, setNewDescription] = useState('');
  const [newDeptId, setNewDeptId] = useState('');
  const [newPriority, setNewPriority] = useState('normal');
  const [newDocFiles, setNewDocFiles] = useState([]);
  const [newImageFiles, setNewImageFiles] = useState([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  
  // Edit Form Inputs
  const [editTitle, setEditTitle] = useState('');
  const [editDescription, setEditDescription] = useState('');
  const [editPriority, setEditPriority] = useState('normal');
  
  // Chat & History States
  const [messages, setMessages] = useState([]);
  const [chatText, setChatText] = useState('');
  const [editHistory, setEditHistory] = useState([]);
  
  // Admin Update Status States
  const [statusNotes, setStatusNotes] = useState('');

  const chatEndRef = useRef(null);

  // File reader helper
  const readFileAsBase64 = (file) => {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.readAsDataURL(file);
      reader.onload = () => resolve(reader.result);
      reader.onerror = error => reject(error);
    });
  };

  const handleViewFile = (file) => {
    if (!file.url || file.url === '#') {
      alert("No viewable file content was found.");
      return;
    }
    const win = window.open();
    if (win) {
      win.document.write(`
        <html>
          <head>
            <title>${file.name}</title>
            <style>
              body { margin: 0; display: flex; align-items: center; justify-content: center; background: #f1f5f9; font-family: sans-serif; }
              iframe, img { width: 100%; height: 100vh; border: none; object-fit: contain; }
            </style>
          </head>
          <body>
            ${file.url.startsWith('data:image/') 
              ? `<img src="${file.url}" alt="${file.name}" />` 
              : `<iframe src="${file.url}"></iframe>`}
          </body>
        </html>
      `);
      win.document.close();
    } else {
      alert("Pop-up window was blocked. Please enable popups in your browser.");
    }
  };

  // 1. Fetch Submissions, Colleges, and Departments
  useEffect(() => {
    let qSub;
    if (currentUser?.role === 'college_user') {
      qSub = query(collection(db, 'submissions'), where('collegeId', '==', currentUser.collegeId));
    } else {
      qSub = collection(db, 'submissions');
    }

    const unsubSub = onSnapshot(qSub, (snap) => {
      const list = [];
      snap.forEach(d => list.push({ id: d.id, ...d.data() }));
      setSubmissions(list);
    });

    const loadMetadata = async () => {
      const colSnap = await getDocs(collection(db, 'colleges'));
      const colList = {};
      colSnap.forEach(d => { colList[d.id] = d.data().name; });
      setColleges(colList);

      const deptSnap = await getDocs(collection(db, 'departments'));
      const deptList = [];
      deptSnap.forEach(d => deptList.push({ id: d.id, ...d.data() }));
      setDepartments(deptList);
    };
    loadMetadata();

    return () => unsubSub();
  }, [currentUser]);

  // 2. Fetch Chat and History logs when a submission is selected
  useEffect(() => {
    if (!selectedSub) return;

    // Chat listener
    const chatQuery = query(
      collection(db, 'messages'), 
      where('submissionId', '==', selectedSub.id),
      orderBy('createdAt', 'asc')
    );
    const unsubChat = onSnapshot(chatQuery, (snap) => {
      const list = [];
      snap.forEach(d => list.push({ id: d.id, ...d.data() }));
      setMessages(list);
      setTimeout(() => chatEndRef.current?.scrollIntoView({ behavior: 'smooth' }), 100);
    });

    // Edit history snapshot lookup
    const historyQuery = query(
      collection(db, 'submission_edit_history'),
      where('submissionId', '==', selectedSub.id),
      orderBy('editedAt', 'desc')
    );
    const unsubHistory = onSnapshot(historyQuery, (snap) => {
      const list = [];
      snap.forEach(d => list.push({ id: d.id, ...d.data() }));
      setEditHistory(list);
    });

    return () => {
      unsubChat();
      unsubHistory();
    };
  }, [selectedSub]);

  // 3. Handle New Submission Upload
  const handleAddSubmit = async (e) => {
    e.preventDefault();
    if (!newDeptId || !newTitle) return;

    try {
      setIsSubmitting(true);
      const selectedDept = departments.find(d => d.id === newDeptId);
      
      // Convert document files to base64 for fallback
      const docFilesData = await Promise.all(
        newDocFiles.map(async (file) => {
          const base64 = await readFileAsBase64(file);
          return {
            name: file.name,
            size: Math.round(file.size / 1024) + ' KB',
            url: base64,
            type: 'document'
          };
        })
      );

      // Convert image files to base64 for fallback
      const imgFilesData = await Promise.all(
        newImageFiles.map(async (file) => {
          const base64 = await readFileAsBase64(file);
          return {
            name: file.name,
            size: Math.round(file.size / 1024) + ' KB',
            url: base64,
            type: 'image'
          };
        })
      );

      let combinedFiles = [...docFilesData, ...imgFilesData];

      // Google Drive Upload try-catch block
      try {
        const functionsUrl = import.meta.env.VITE_CLOUD_FUNCTIONS_URL;
        if (functionsUrl && (newDocFiles.length > 0 || newImageFiles.length > 0)) {
          const formData = new FormData();
          formData.append('collegeName', colleges[currentUser.collegeId] || 'Default College');
          formData.append('departmentName', selectedDept?.name || 'Default Department');
          
          newDocFiles.forEach((file, index) => {
            formData.append(`doc_${index}`, file);
          });
          newImageFiles.forEach((file, index) => {
            formData.append(`img_${index}`, file);
          });

          const uploadRes = await fetch(`${functionsUrl}/uploadToDrive`, {
            method: 'POST',
            body: formData
          });

          if (uploadRes.ok) {
            const driveData = await uploadRes.json();
            if (driveData.success && driveData.files) {
              // Map uploaded Google Drive details back to files!
              combinedFiles = combinedFiles.map(f => {
                const driveMatch = driveData.files.find(df => df.name === f.name);
                if (driveMatch) {
                  return {
                    ...f,
                    googleDriveFileId: driveMatch.googleDriveFileId,
                    url: driveMatch.googleDriveUrl // Set Google Drive URL as main download/view url!
                  };
                }
                return f;
              });
            }
          }
        }
      } catch (err) {
        console.error("Google Drive Upload failed, falling back to local base64:", err);
      }

      const docRef = await addDoc(collection(db, 'submissions'), {
        collegeId: currentUser.collegeId,
        departmentId: newDeptId,
        departmentName: selectedDept?.name || '',
        title: newTitle,
        description: newDescription,
        status: 'pending',
        priority: newPriority,
        editCount: 0,
        createdBy: currentUser.uid,
        createdByName: currentUser.name,
        createdAt: Timestamp.now(),
        updatedAt: Timestamp.now(),
        files: combinedFiles
      });

      // Clear Form
      setNewTitle('');
      setNewDescription('');
      setNewDeptId('');
      setNewPriority('normal');
      setNewDocFiles([]);
      setNewImageFiles([]);
      setShowAddModal(false);
      alert("Submission added successfully!");
    } catch (err) {
      console.error(err);
      alert("Failed to submit work record: " + err.message);
    } finally {
      setIsSubmitting(false);
    }
  };

  // 4. Handle Edit Updates
  const handleEditSubmit = async (e) => {
    if (e && e.preventDefault) e.preventDefault();
    if (selectedSub.status !== 'pending' && currentUser.role === 'college_user') {
      alert("Only submissions in Pending status can be updated!");
      return;
    }

    try {
      // Log edit history snapshot
      await addDoc(collection(db, 'submission_edit_history'), {
        submissionId: selectedSub.id,
        title: selectedSub.title,
        description: selectedSub.description,
        priority: selectedSub.priority,
        editCount: (selectedSub.editCount || 0) + 1,
        editedBy: currentUser.uid,
        editedByName: currentUser.name,
        editedAt: Timestamp.now()
      });

      // Commit changes to main document
      const subRef = doc(db, 'submissions', selectedSub.id);
      await updateDoc(subRef, {
        title: editTitle,
        description: editDescription,
        priority: editPriority,
        editCount: (selectedSub.editCount || 0) + 1,
        updatedAt: Timestamp.now()
      });

      setSelectedSub(prev => ({
        ...prev,
        title: editTitle,
        description: editDescription,
        priority: editPriority,
        editCount: (prev.editCount || 0) + 1
      }));
      setIsEditing(false);
      alert("Submission updated successfully!");
    } catch (err) {
      console.error(err);
      alert("Failed to update submission: " + err.message);
    }
  };

  // 5. Send Chat Message
  const handleSendMessage = async (e) => {
    e.preventDefault();
    if (!chatText.trim()) return;

    try {
      await addDoc(collection(db, 'messages'), {
        submissionId: selectedSub.id,
        senderId: currentUser.uid,
        senderName: currentUser.name,
        messageText: chatText,
        createdAt: Timestamp.now()
      });
      setChatText('');
    } catch (err) {
      console.error(err);
    }
  };

  // 6. Admin Update Status Handler
  const handleUpdateStatus = async (newStatus) => {
    if (currentUser.role === 'admin') {
      alert("Central Administrators are configured with View-Only permissions for submissions.");
      return;
    }

    try {
      const subRef = doc(db, 'submissions', selectedSub.id);
      await updateDoc(subRef, {
        status: newStatus,
        processingNotes: newStatus === 'processing' ? statusNotes : selectedSub.processingNotes,
        completionNotes: newStatus === 'completed' ? statusNotes : selectedSub.completionNotes,
        updatedAt: Timestamp.now()
      });

      setSelectedSub(prev => ({
        ...prev,
        status: newStatus,
        processingNotes: newStatus === 'processing' ? statusNotes : prev.processingNotes,
        completionNotes: newStatus === 'completed' ? statusNotes : prev.completionNotes
      }));
      setStatusNotes('');
      alert(`Status updated to ${newStatus} successfully!`);
    } catch (e) {
      console.error(e);
    }
  };

  const myDeptOptions = departments.filter(d => d.collegeId === currentUser?.collegeId);

  return (
    <div className="container-fluid py-4">
      {/* Title & Toolbar Area */}
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 className="fw-bold text-dark m-0" style={{ fontSize: '1.75rem' }}>Submissions Desk</h2>
        </div>
        {currentUser?.role === 'college_user' && (
          <button 
            className="btn btn-primary fw-bold px-4 d-flex align-items-center gap-2"
            onClick={() => setShowAddModal(true)}
            style={{ backgroundColor: '#0C4DA2', borderColor: '#0C4DA2', height: '46px', borderRadius: '10px' }}
          >
            <i className="bi bi-file-earmark-plus"></i> Submit Work Request
          </button>
        )}
      </div>

      {/* Tabs & Table Container */}
      <div className="card border-0 rounded-4 shadow-sm p-4 bg-white mb-4">
        {/* Tab Switcher Pills */}
        <div className="border-bottom mb-4">
          <ul className="nav nav-tabs border-bottom-0">
            <li className="nav-item">
              <button 
                className="nav-link active fw-bold text-primary border-0 border-bottom border-primary border-3 bg-transparent pb-3"
                style={{ color: '#0C4DA2', borderColor: '#0C4DA2' }}
              >
                All Submissions ({submissions.length})
              </button>
            </li>
          </ul>
        </div>

        {/* Search & Showing entries bar */}
        <div className="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
          <div className="text-muted small fw-semibold">
            Showing 1 to {submissions.length} of {submissions.length} work records
          </div>
          <div className="d-flex gap-2">
            <button className="btn btn-light border p-2 d-flex align-items-center justify-content-center" style={{ width: '38px', height: '38px', borderRadius: '8px' }}>
              <i className="bi bi-grid text-muted"></i>
            </button>
            <button className="btn btn-primary p-2 d-flex align-items-center justify-content-center" style={{ width: '38px', height: '38px', borderRadius: '8px', backgroundColor: '#0C4DA2', borderColor: '#0C4DA2' }}>
              <i className="bi bi-list-task text-white"></i>
            </button>
          </div>
        </div>

        {/* Submissions Table */}
        <div className="table-responsive">
          <table className="table table-hover align-middle m-0">
            <thead className="table-light" style={{ borderBottom: '2.5px solid #4EB849' }}>
              <tr style={{ fontSize: '0.78rem', textTransform: 'uppercase', color: '#475569' }}>
                <th>Task ID ⇅</th>
                <th>Department ⇅</th>
                <th style={{ minWidth: '320px' }}>Submission Title ⇅</th>
                <th className="text-center">File Attachment ⇅</th>
                <th>Submitted By ⇅</th>
                <th>Status ⇅</th>
                <th className="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              {submissions.map((sub, index) => {
                const taskPillCode = `TASK-2026-${String(index + 1).padStart(6, '0')}`;
                
                // Find first attachment if exists
                const firstFile = sub.files && sub.files.length > 0 ? sub.files[0] : null;

                return (
                  <tr key={sub.id}>
                    <td>
                      <span className="ref-code-pill px-2.5 py-1.5 small rounded-3 fw-bold d-inline-block text-center" style={{
                        backgroundColor: 'rgba(78, 184, 73, 0.08)',
                        color: '#4EB849',
                        fontSize: '0.78rem'
                      }}>
                        {taskPillCode}
                      </span>
                    </td>
                    <td className="fw-bold text-dark" style={{ fontSize: '0.88rem' }}>
                      {sub.departmentName}
                    </td>
                    <td>
                      <div className="fw-bold mb-1" style={{ color: '#0C4DA2', fontSize: '0.925rem' }}>{sub.title}</div>
                      <div className="text-muted small text-truncate" style={{ maxWidth: '350px' }}>{sub.description}</div>
                    </td>
                    <td className="text-center">
                      {firstFile ? (
                        <div className="d-flex flex-column align-items-center">
                          <a href={firstFile.url} className="d-flex align-items-center justify-content-center rounded-circle" style={{ backgroundColor: 'rgba(78, 184, 73, 0.1)', width: '32px', height: '32px', color: '#4EB849' }}>
                            <i className="bi bi-paperclip fs-5"></i>
                          </a>
                          <span className="text-muted small d-block mt-1" style={{ fontSize: '0.68rem', maxWidth: '100px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {firstFile.name}
                          </span>
                        </div>
                      ) : (
                        <span className="text-muted">&mdash;</span>
                      )}
                    </td>
                    <td>
                      <div className="fw-bold text-dark small">{sub.createdByName}</div>
                      <span className="text-muted small" style={{ fontSize: '0.72rem' }}>
                        {sub.createdAt ? new Date(sub.createdAt.seconds * 1000).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : ''}
                      </span>
                    </td>
                    <td>
                      {sub.status === 'completed' && (
                        <span className="badge bg-success bg-opacity-10 text-success px-2.5 py-1.5 rounded-pill small fw-semibold" style={{ fontSize: '0.78rem' }}>
                          &bull; Completed
                        </span>
                      )}
                      {sub.status === 'processing' && (
                        <span className="badge bg-warning bg-opacity-10 text-warning px-2.5 py-1.5 rounded-pill small fw-semibold" style={{ fontSize: '0.78rem' }}>
                          &bull; Processing
                        </span>
                      )}
                      {sub.status === 'pending' && (
                        <span className="badge bg-danger bg-opacity-10 text-danger px-2.5 py-1.5 rounded-pill small fw-semibold" style={{ fontSize: '0.78rem' }}>
                          &bull; Pending
                        </span>
                      )}
                    </td>
                    <td className="text-end">
                      <div className="d-flex gap-2 justify-content-end">
                        <button
                          className="btn btn-sm btn-outline-primary border p-1.5 d-flex align-items-center justify-content-center"
                          style={{ borderRadius: '8px', width: '34px', height: '34px' }}
                          onClick={() => {
                            setSelectedSub(sub);
                            setEditTitle(sub.title);
                            setEditDescription(sub.description);
                            setEditPriority(sub.priority);
                            setShowDetailsModal(true);
                          }}
                        >
                          <i className="bi bi-eye"></i>
                        </button>
                        <button
                          className="btn btn-sm btn-outline-secondary border p-1.5 d-flex align-items-center justify-content-center"
                          style={{ borderRadius: '8px', width: '34px', height: '34px' }}
                          onClick={() => {
                            setSelectedSub(sub);
                            setEditTitle(sub.title);
                            setEditDescription(sub.description);
                            setEditPriority(sub.priority);
                            setShowDetailsModal(true);
                          }}
                        >
                          <i className="bi bi-chat-dots"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Footer entries & pagination */}
        <div className="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3">
          <div className="d-flex align-items-center gap-2 small text-muted">
            <span>Show</span>
            <select className="form-select form-select-sm" style={{ width: '70px', borderRadius: '8px' }}>
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
            </select>
            <span>entries</span>
          </div>
          <div className="d-flex gap-1">
            <button className="btn btn-outline-secondary btn-sm px-3" style={{ borderRadius: '8px' }}>Previous</button>
            <button className="btn btn-primary btn-sm px-3" style={{ borderRadius: '8px', backgroundColor: '#0C4DA2', borderColor: '#0C4DA2' }}>1</button>
            <button className="btn btn-outline-secondary btn-sm px-3" style={{ borderRadius: '8px' }}>2</button>
            <button className="btn btn-outline-secondary btn-sm px-3" style={{ borderRadius: '8px' }}>Next</button>
          </div>
        </div>
      </div>

      {/* ADD SUBMISSION MODAL */}
      {showAddModal && (
        <div className="modal show d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog modal-lg modal-dialog-centered">
            <div className="modal-content rounded-4 border-0">
              <form onSubmit={handleAddSubmit}>
                <div className="modal-header border-bottom-0 pt-4 px-4">
                  <h5 className="modal-title fw-bold text-dark">Submit Work Record</h5>
                  <button type="button" className="btn-close" onClick={() => setShowAddModal(false)}></button>
                </div>
                <div className="modal-body px-4 pb-4">
                  <div className="mb-3">
                    <label className="form-label small fw-bold text-muted">Select Department</label>
                    <select 
                      className="form-select bg-light border-0" 
                      value={newDeptId}
                      onChange={(e) => setNewDeptId(e.target.value)}
                      required
                    >
                      <option value="">-- Choose Department --</option>
                      {myDeptOptions.map(d => (
                        <option key={d.id} value={d.id}>{d.name}</option>
                      ))}
                    </select>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-bold text-muted">Submission Title</label>
                    <input 
                      type="text" 
                      className="form-control bg-light border-0"
                      value={newTitle}
                      onChange={(e) => setNewTitle(e.target.value)}
                      placeholder="Enter submission title" 
                      required 
                    />
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-bold text-muted">Description / Notes</label>
                    <textarea 
                      className="form-control bg-light border-0" 
                      rows="4"
                      value={newDescription}
                      onChange={(e) => setNewDescription(e.target.value)}
                      placeholder="Provide detailed description of work done..."
                    ></textarea>
                  </div>

                  <div className="row g-3 mb-3">
                    <div className="col-md-6">
                      <label className="form-label small fw-bold text-muted">PDF / Documents Upload</label>
                      <input 
                        type="file" 
                        className="form-control bg-light border-0" 
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.txt"
                        multiple 
                        onChange={(e) => setNewDocFiles(Array.from(e.target.files))}
                      />
                    </div>

                    <div className="col-md-6">
                      <label className="form-label small fw-bold text-muted">Images Upload</label>
                      <input 
                        type="file" 
                        className="form-control bg-light border-0" 
                        accept="image/*"
                        multiple 
                        onChange={(e) => setNewImageFiles(Array.from(e.target.files))}
                      />
                    </div>
                  </div>

                  <div className="mb-3">
                    <label className="form-label small fw-bold text-muted">Submission Priority</label>
                    <select 
                      className="form-select bg-light border-0"
                      value={newPriority}
                      onChange={(e) => setNewPriority(e.target.value)}
                    >
                      <option value="low">Low Priority</option>
                      <option value="normal">Normal Priority</option>
                      <option value="high">High Priority</option>
                      <option value="urgent">Urgent Priority</option>
                    </select>
                  </div>
                </div>
                <div className="modal-footer border-top-0 pt-0 px-4 pb-4">
                  <button type="button" className="btn btn-light fw-bold" onClick={() => setShowAddModal(false)} disabled={isSubmitting}>Cancel</button>
                  <button 
                    type="submit" 
                    className="btn btn-primary fw-bold d-flex align-items-center gap-2" 
                    disabled={isSubmitting}
                    style={{ backgroundColor: '#0C4DA2', borderColor: '#0C4DA2' }}
                  >
                    {isSubmitting ? (
                      <>
                        <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span>Uploading files...</span>
                      </>
                    ) : (
                      <span>Upload & Submit</span>
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* DETAILS, CHAT & EDIT HISTORY MODAL (Screenshot 4) */}
      {showDetailsModal && selectedSub && (
        <div className="modal show d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog modal-xl modal-dialog-centered">
            <div className="modal-content rounded-4 border-0 overflow-hidden">
              
              {/* Teal Solid Header (Screenshot 4) */}
              <div className="px-4 py-3 d-flex justify-content-between align-items-center text-white" style={{ backgroundColor: '#10b981' }}>
                <div className="d-flex align-items-center gap-2">
                  <i className="bi bi-file-earmark-text fs-5"></i>
                  <span className="fw-bold" style={{ letterSpacing: '0.02em', fontSize: '1.05rem' }}>
                    TASK-2026-{(submissions.findIndex(s => s.id === selectedSub.id) + 1).toString().padStart(6, '0')} Details
                  </span>
                </div>
                <button type="button" className="btn-close btn-close-white border-0 bg-transparent text-white" onClick={() => setShowDetailsModal(false)} style={{ fontSize: '1.25rem' }}>
                  <i className="bi bi-x-lg"></i>
                </button>
              </div>

              <div className="modal-body px-4 py-4" style={{ backgroundColor: '#f8fafc' }}>
                
                {/* 4 Cards Parameter Row (Screenshot 4) */}
                <div className="row g-3 mb-4">
                  <div className="col-12 col-md-6 col-lg-3">
                    <div className="card border rounded-4 p-3 bg-white d-flex flex-row align-items-center gap-3 shadow-xs h-100">
                      <div className="rounded-circle d-flex align-items-center justify-content-center" style={{ backgroundColor: '#edfbf0', width: '42px', height: '42px', color: '#4EB849' }}>
                        <i className="bi bi-bank fs-5"></i>
                      </div>
                      <div>
                        <div className="text-muted small fw-bold text-uppercase" style={{ fontSize: '0.625rem', letterSpacing: '0.05em' }}>College</div>
                        <div className="fw-bold text-dark small">{colleges[selectedSub.collegeId] || 'Institution'}</div>
                      </div>
                    </div>
                  </div>
                  
                  <div className="col-12 col-md-6 col-lg-3">
                    <div className="card border rounded-4 p-3 bg-white d-flex flex-row align-items-center gap-3 shadow-xs h-100">
                      <div className="rounded-circle d-flex align-items-center justify-content-center" style={{ backgroundColor: '#eef5fc', width: '42px', height: '42px', color: '#0C4DA2' }}>
                        <i className="bi bi-activity fs-5"></i>
                      </div>
                      <div>
                        <div className="text-muted small fw-bold text-uppercase" style={{ fontSize: '0.625rem', letterSpacing: '0.05em' }}>Department</div>
                        <div className="fw-bold text-dark small">{selectedSub.departmentName || 'General'}</div>
                      </div>
                    </div>
                  </div>

                  <div className="col-12 col-md-6 col-lg-3">
                    <div className="card border rounded-4 p-3 bg-white d-flex flex-row align-items-center gap-3 shadow-xs h-100">
                      <div className="rounded-circle d-flex align-items-center justify-content-center" style={{ backgroundColor: '#edfbf0', width: '42px', height: '42px', color: '#4EB849' }}>
                        <i className="bi bi-calendar-event fs-5"></i>
                      </div>
                      <div>
                        <div className="text-muted small fw-bold text-uppercase" style={{ fontSize: '0.625rem', letterSpacing: '0.05em' }}>Created On</div>
                        <div className="fw-bold text-dark small">
                          {selectedSub.createdAt ? new Date(selectedSub.createdAt.seconds * 1000).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' }) : ''}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="col-12 col-md-6 col-lg-3">
                    <div className="card border rounded-4 p-3 bg-white d-flex flex-row align-items-center gap-3 shadow-xs h-100">
                      <div className="rounded-circle d-flex align-items-center justify-content-center" style={{ backgroundColor: '#eef5fc', width: '42px', height: '42px', color: '#0C4DA2' }}>
                        <i className="bi bi-person fs-5"></i>
                      </div>
                      <div>
                        <div className="text-muted small fw-bold text-uppercase" style={{ fontSize: '0.625rem', letterSpacing: '0.05em' }}>Created By</div>
                        <div className="fw-bold text-dark small">{selectedSub.createdByName}</div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Title & Description card / edit fields */}
                {isEditing ? (
                  <div className="mb-4 card border rounded-4 p-4 bg-white shadow-xs">
                    <div className="mb-3">
                      <label className="form-label small fw-bold text-muted text-uppercase" style={{ fontSize: '0.65rem' }}>Title</label>
                      <input 
                        type="text" 
                        className="form-control" 
                        value={editTitle} 
                        onChange={(e) => setEditTitle(e.target.value)} 
                        required 
                      />
                    </div>
                    <div className="mb-0">
                      <label className="form-label small fw-bold text-muted text-uppercase" style={{ fontSize: '0.65rem' }}>Description</label>
                      <textarea 
                        className="form-control" 
                        rows="3" 
                        value={editDescription} 
                        onChange={(e) => setEditDescription(e.target.value)}
                      ></textarea>
                    </div>
                  </div>
                ) : (
                  <div className="mb-4">
                    <span className="small text-muted fw-bold text-uppercase" style={{ fontSize: '0.65rem' }}>Title</span>
                    <h4 className="fw-bold mb-2" style={{ color: '#0C4DA2' }}>{selectedSub.title}</h4>
                    <span className="small text-muted fw-bold text-uppercase" style={{ fontSize: '0.65rem' }}>Description</span>
                    <div className="card border rounded-4 p-3 bg-white shadow-xs">
                      <div className="text-dark small" style={{ lineHeight: '1.6' }}>{selectedSub.description || 'No description provided.'}</div>
                    </div>
                  </div>
                )}

                {/* Status & Priority cards / edit fields */}
                {isEditing ? (
                  <div className="row g-3 mb-4">
                    <div className="col-12 col-md-6">
                      <div className="card border rounded-4 p-3 bg-white d-flex flex-row align-items-center justify-content-between shadow-xs h-100">
                        <div className="d-flex align-items-center gap-2">
                          <span className="rounded-circle bg-success bg-opacity-10 d-inline-block" style={{ width: '8px', height: '8px' }}></span>
                          <span className="small fw-bold text-muted text-uppercase" style={{ fontSize: '0.65rem' }}>Operational Status</span>
                        </div>
                        <span className={`badge px-3 py-1.5 fw-bold text-uppercase`} style={{ 
                          backgroundColor: selectedSub.status === 'completed' ? '#10b981' : (selectedSub.status === 'processing' ? '#f59e0b' : '#ef4444'),
                          color: '#ffffff',
                          borderRadius: '6px',
                          fontSize: '0.72rem'
                        }}>
                          {selectedSub.status}
                        </span>
                      </div>
                    </div>
                    
                    <div className="col-12 col-md-6">
                      <div className="card border rounded-4 p-3 bg-white d-flex flex-row align-items-center justify-content-between shadow-xs h-100">
                        <div className="d-flex align-items-center gap-2">
                          <i className="bi bi-graph-up-arrow text-primary"></i>
                          <span className="small fw-bold text-muted text-uppercase" style={{ fontSize: '0.65rem' }}>Priority</span>
                        </div>
                        <select 
                          className="form-select form-select-sm" 
                          style={{ width: '130px', fontWeight: 'bold' }}
                          value={editPriority}
                          onChange={(e) => setEditPriority(e.target.value)}
                        >
                          <option value="low">LOW</option>
                          <option value="normal">NORMAL</option>
                          <option value="high">HIGH</option>
                          <option value="urgent">URGENT</option>
                        </select>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="row g-3 mb-4">
                    <div className="col-12 col-md-6">
                      <div className="card border rounded-4 p-3 bg-white d-flex flex-row align-items-center justify-content-between shadow-xs h-100">
                        <div className="d-flex align-items-center gap-2">
                          <span className="rounded-circle bg-success bg-opacity-10 d-inline-block" style={{ width: '8px', height: '8px' }}></span>
                          <span className="small fw-bold text-muted text-uppercase" style={{ fontSize: '0.65rem' }}>Operational Status</span>
                        </div>
                        <span className={`badge px-3 py-1.5 fw-bold text-uppercase`} style={{ 
                          backgroundColor: selectedSub.status === 'completed' ? '#10b981' : (selectedSub.status === 'processing' ? '#f59e0b' : '#ef4444'),
                          color: '#ffffff',
                          borderRadius: '6px',
                          fontSize: '0.72rem'
                        }}>
                          {selectedSub.status}
                        </span>
                      </div>
                    </div>
                    
                    <div className="col-12 col-md-6">
                      <div className="card border rounded-4 p-3 bg-white d-flex flex-row align-items-center justify-content-between shadow-xs h-100">
                        <div className="d-flex align-items-center gap-2">
                          <i className="bi bi-graph-up-arrow text-primary"></i>
                          <span className="small fw-bold text-muted text-uppercase" style={{ fontSize: '0.65rem' }}>Priority</span>
                        </div>
                        <span className="badge px-3 py-1.5 fw-bold text-uppercase" style={{ 
                          backgroundColor: '#64748b',
                          color: '#ffffff',
                          borderRadius: '6px',
                          fontSize: '0.72rem'
                        }}>
                          {selectedSub.priority}
                        </span>
                      </div>
                    </div>
                  </div>
                )}

                {/* Documents & Images Sections */}
                {(() => {
                  const docFiles = selectedSub.files ? selectedSub.files.filter(f => f.type === 'document' || !f.name.match(/\.(jpg|jpeg|png|gif)$/i)) : [];
                  const imgFiles = selectedSub.files ? selectedSub.files.filter(f => f.type === 'image' || f.name.match(/\.(jpg|jpeg|png|gif)$/i)) : [];
                  return (
                    <div className="row g-4 mb-4">
                      
                      {/* Documents attachment box */}
                      <div className="col-12 col-lg-6">
                        <div className="card border rounded-4 p-4 bg-white shadow-xs h-100">
                          <div className="d-flex align-items-center gap-2 mb-3">
                            <i className="bi bi-file-earmark-check text-success fs-5"></i>
                            <h6 className="fw-bold mb-0 text-dark">Documents Attachment</h6>
                          </div>
                          <div className="d-flex flex-column gap-2">
                            {docFiles.length > 0 ? (
                              docFiles.map((file, idx) => (
                                <div key={idx} className="d-flex align-items-center justify-content-between p-2.5 border rounded-3 bg-light">
                                  <span className="small fw-semibold text-truncate" style={{ maxWidth: '240px' }}>
                                    <i className="bi bi-file-earmark-text me-1.5 text-secondary"></i>
                                    {file.name}
                                  </span>
                                  <div className="d-flex gap-2">
                                    <button 
                                      onClick={() => handleViewFile(file)}
                                      className="btn btn-sm fw-bold px-3 py-1" 
                                      style={{ 
                                        fontSize: '0.7rem', 
                                        backgroundColor: '#f1f5f9', 
                                        color: '#475569', 
                                        border: '1px solid #cbd5e1',
                                        borderRadius: '6px'
                                      }}
                                      onMouseOver={(e) => { e.currentTarget.style.backgroundColor = '#e2e8f0'; }}
                                      onMouseOut={(e) => { e.currentTarget.style.backgroundColor = '#f1f5f9'; }}
                                    >
                                      View
                                    </button>
                                    <a 
                                      href={file.url} 
                                      download={file.name}
                                      className="btn btn-sm fw-bold text-white px-3 py-1 text-decoration-none" 
                                      style={{ 
                                        fontSize: '0.7rem', 
                                        backgroundColor: '#0C4DA2', 
                                        border: 'none',
                                        borderRadius: '6px'
                                      }}
                                      onMouseOver={(e) => { e.currentTarget.style.backgroundColor = '#0a3d82'; }}
                                      onMouseOut={(e) => { e.currentTarget.style.backgroundColor = '#0C4DA2'; }}
                                    >
                                      Download
                                    </a>
                                  </div>
                                </div>
                              ))
                            ) : (
                              <span className="small text-muted">No documents uploaded.</span>
                            )}
                          </div>
                        </div>
                      </div>

                      {/* Images attachment box */}
                      <div className="col-12 col-lg-6">
                        <div className="card border rounded-4 p-4 bg-white shadow-xs h-100">
                          <div className="d-flex align-items-center gap-2 mb-3">
                            <i className="bi bi-image text-primary fs-5"></i>
                            <h6 className="fw-bold mb-0 text-dark">Images Attachment</h6>
                          </div>
                          <div className="row g-2">
                            {imgFiles.length > 0 ? (
                              imgFiles.map((file, idx) => (
                                <div key={idx} className="col-4" onClick={() => handleViewFile(file)} style={{ cursor: 'pointer' }}>
                                  <div 
                                    className="border rounded-3 p-2 text-center" 
                                    style={{ backgroundColor: '#f8fafc', transition: 'all 0.2s' }}
                                    onMouseOver={(e) => { e.currentTarget.style.borderColor = '#0C4DA2'; e.currentTarget.style.backgroundColor = '#f0f4fa'; }}
                                    onMouseOut={(e) => { e.currentTarget.style.borderColor = '#dee2e6'; e.currentTarget.style.backgroundColor = '#f8fafc'; }}
                                  >
                                    <div className="text-muted small mb-1" style={{ fontSize: '0.625rem' }}>Image {idx + 1}</div>
                                    {file.url && file.url.startsWith('data:image/') ? (
                                      <img src={file.url} alt={file.name} className="img-fluid rounded mb-2" style={{ maxHeight: '50px', objectFit: 'cover' }} />
                                    ) : (
                                      <i className="bi bi-image fs-1 text-secondary d-block my-2"></i>
                                    )}
                                    <span className="small text-muted text-truncate d-block" style={{ fontSize: '0.68rem' }} title={file.name}>
                                      {file.name}
                                    </span>
                                  </div>
                                </div>
                              ))
                            ) : (
                              <div className="col-12">
                                <span className="small text-muted">No images uploaded.</span>
                              </div>
                            )}
                          </div>
                        </div>
                      </div>

                    </div>
                  );
                })()}

                {/* Processing and Completion URLs */}
                <div className="row g-3 mb-4">
                  <div className="col-12 col-md-4">
                    <label className="form-label small fw-bold text-muted">Update URL</label>
                    <div className="input-group">
                      <span className="input-group-text bg-white border"><i className="bi bi-link-45deg"></i></span>
                      <input type="text" className="form-control bg-white" value={selectedSub.updateUrl || ''} readOnly placeholder="Not set" />
                    </div>
                  </div>
                  
                  <div className="col-12 col-md-4">
                    <label className="form-label small fw-bold text-muted">Processing URL</label>
                    <div className="input-group">
                      <span className="input-group-text bg-white border"><i className="bi bi-link-45deg"></i></span>
                      <input type="text" className="form-control bg-white" value={selectedSub.processingUrl || ''} readOnly placeholder="Not set" />
                    </div>
                  </div>

                  <div className="col-12 col-md-4">
                    <label className="form-label small fw-bold text-muted">Completed URL</label>
                    <div className="input-group">
                      <span className="input-group-text bg-white border"><i className="bi bi-link-45deg"></i></span>
                      <input type="text" className="form-control bg-white" value={selectedSub.completedUrl || ''} readOnly placeholder="Not set" />
                    </div>
                  </div>
                </div>

                {/* Notes Sections (Processing & Completion) */}
                <div className="row g-3 mb-4">
                  <div className="col-12 col-md-6">
                    <div className="d-flex align-items-center gap-1.5 mb-2">
                      <i className="bi bi-chat-left-text text-muted"></i>
                      <label className="form-label small fw-bold text-muted m-0">Processing Notes (Admin)</label>
                    </div>
                    <textarea className="form-control bg-white" rows="3" readOnly value={selectedSub.processingNotes || 'None.'}></textarea>
                  </div>

                  <div className="col-12 col-md-6">
                    <div className="d-flex align-items-center gap-1.5 mb-2">
                      <i className="bi bi-chat-left-text text-muted"></i>
                      <label className="form-label small fw-bold text-muted m-0">Completion Notes (Admin)</label>
                    </div>
                    <textarea className="form-control bg-white" rows="3" readOnly value={selectedSub.completionNotes || 'None.'}></textarea>
                  </div>
                </div>

                {/* Audit notes and updates (Super Admin Only) */}
                {currentUser.role === 'super_admin' && (
                  <div className="card bg-light border-0 rounded-4 p-4 mb-4">
                    <h6 className="fw-bold mb-3 text-warning">Audit Action Panel</h6>
                    <div className="mb-3">
                      <label className="form-label small fw-bold text-muted">Audit Notes</label>
                      <textarea
                        className="form-control border-0 bg-white"
                        rows="2"
                        value={statusNotes}
                        onChange={(e) => setStatusNotes(e.target.value)}
                        placeholder="Enter reasons, notes, or comments for status update..."
                      ></textarea>
                    </div>
                    <div className="d-flex gap-2">
                      <button className="btn btn-warning text-white fw-bold btn-sm px-3" onClick={() => handleUpdateStatus('processing')}>Set Processing</button>
                      <button className="btn btn-success fw-bold btn-sm px-3" onClick={() => handleUpdateStatus('completed')}>Set Completed</button>
                    </div>
                  </div>
                )}

                {/* Communication Chat Section */}
                <div className="card border-0 rounded-4 shadow-sm">
                  <div className="card-header bg-white border-bottom py-3 px-4">
                    <h6 className="fw-bold mb-0 text-dark">Live Message Box</h6>
                  </div>
                  <div className="card-body p-3" style={{ height: '240px', overflowY: 'auto', background: '#f8fafc' }}>
                    {messages.map((msg) => (
                      <div key={msg.id} className={`d-flex flex-column mb-3 ${msg.senderId === currentUser.uid ? 'align-items-end' : 'align-items-start'}`}>
                        <div className="small fw-bold text-muted mb-1">{msg.senderName}</div>
                        <div className="p-3 rounded-4 shadow-xs" style={{
                          maxWidth: '85%',
                          backgroundColor: msg.senderId === currentUser.uid ? '#0C4DA2' : '#ffffff',
                          color: msg.senderId === currentUser.uid ? '#ffffff' : '#0f172a'
                        }}>
                          <p className="mb-0 small">{msg.messageText}</p>
                        </div>
                      </div>
                    ))}
                    <div ref={chatEndRef} />
                  </div>
                  <div className="card-footer bg-white border-0 p-3">
                    <form onSubmit={handleSendMessage} className="input-group">
                      <input 
                        type="text" 
                        className="form-control bg-light border" 
                        value={chatText}
                        onChange={(e) => setChatText(e.target.value)}
                        placeholder="Type a message..."
                        style={{ borderRadius: '10px 0 0 10px', padding: '0.6rem' }}
                      />
                      <button className="btn btn-primary border px-4" type="submit" style={{ backgroundColor: '#0C4DA2', borderColor: '#0C4DA2', borderRadius: '0 10px 10px 0' }}>
                        Send
                      </button>
                    </form>
                  </div>
                </div>

              </div>

              {/* Modal Footer (Screenshot 4) */}
              <div className="modal-footer border-top px-4 py-3 bg-light d-flex justify-content-end gap-2">
                <button type="button" className="btn btn-outline-secondary fw-bold px-4 py-2" onClick={() => { setShowDetailsModal(false); setIsEditing(false); }} style={{ borderRadius: '8px' }}>Close</button>
                {currentUser.role === 'college_user' && selectedSub.status === 'pending' && (
                  <button 
                    type="button" 
                    className="btn btn-primary fw-bold px-4 py-2 d-flex align-items-center gap-1.5" 
                    onClick={() => {
                      if (isEditing) {
                        handleEditSubmit();
                      } else {
                        setIsEditing(true);
                      }
                    }} 
                    style={{ borderRadius: '8px', backgroundColor: isEditing ? '#10b981' : '#0C4DA2', borderColor: isEditing ? '#10b981' : '#0C4DA2' }}
                  >
                    <i className={isEditing ? "bi bi-check-lg" : "bi bi-pencil"}></i>
                    <span>{isEditing ? "Save Changes" : "Edit Task"}</span>
                  </button>
                )}
              </div>

            </div>
          </div>
        </div>
      )}
    </div>
  );
}
