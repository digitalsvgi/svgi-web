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
  const [newFiles, setNewFiles] = useState([]);
  
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
      const selectedDept = departments.find(d => d.id === newDeptId);
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
        // Mock attached files array for demo/test uploads
        files: newFiles.map(f => ({
          name: f.name,
          size: Math.round(f.size / 1024) + ' KB',
          url: '#',
          googleDriveFileId: 'mock_gd_' + Math.random().toString(36).substr(2, 9)
        }))
      });

      // Clear Form
      setNewTitle('');
      setNewDescription('');
      setNewDeptId('');
      setNewPriority('normal');
      setNewFiles([]);
      setShowAddModal(false);
    } catch (err) {
      console.error(err);
    }
  };

  // 4. Handle Edit Updates
  const handleEditSubmit = async (e) => {
    e.preventDefault();
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
      alert("Submission updated successfully!");
    } catch (err) {
      console.error(err);
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
    <div className="container-fluid py-4 bg-white rounded-4 shadow-sm">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h5 className="fw-bold text-dark m-0" style={{ color: '#4EB849' }}>Submissions Desk</h5>
          <p className="text-muted small m-0">Track files, status logs, and updates across all departments.</p>
        </div>
        {currentUser?.role === 'college_user' && (
          <button 
            className="btn btn-primary fw-bold px-4 rounded-3 shadow-sm border-0"
            onClick={() => setShowAddModal(true)}
            style={{ backgroundColor: '#0C4DA2' }}
          >
            <i className="bi bi-plus-lg me-2"></i>New Submission
          </button>
        )}
      </div>

      {/* Submissions Table */}
      <div className="table-responsive">
        <table className="table table-hover align-middle">
          <thead className="table-light">
            <tr>
              <th style={{ minWidth: '100px' }}>Task ID</th>
              <th style={{ minWidth: '400px' }}>Submission Title</th>
              <th style={{ minWidth: '280px' }}>College & Dept</th>
              <th style={{ minWidth: '130px' }}>Status</th>
              <th style={{ minWidth: '100px' }}>Priority</th>
              <th style={{ minWidth: '150px' }}>Latest Date</th>
              <th style={{ minWidth: '100px' }} className="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            {submissions.map((sub) => {
              const taskPillCode = `CWM-SUB-${sub.id.substr(0, 4).toUpperCase()}`;
              return (
                <tr key={sub.id}>
                  <td>
                    <span className="ref-code-pill px-2 py-1 small rounded-3 fw-bold" style={{
                      backgroundColor: 'rgba(78, 184, 73, 0.08)',
                      color: '#4EB849'
                    }}>
                      {taskPillCode}
                    </span>
                  </td>
                  <td>
                    <div className="fw-bold text-dark mb-1">{sub.title}</div>
                    {/* Attachments List */}
                    {sub.files && sub.files.map((file, idx) => (
                      <div key={idx} className="small mt-1">
                        <a href={file.url} className="ref-attachment-link text-decoration-none fw-semibold small" style={{ color: '#4EB849' }}>
                          <i className="bi bi-paperclip me-1"></i>{file.name}
                        </a>
                      </div>
                    ))}
                  </td>
                  <td>
                    <div className="small fw-bold text-dark">{colleges[sub.collegeId] || 'Institution'}</div>
                    <span className="text-muted small">{sub.departmentName}</span>
                  </td>
                  <td>
                    {sub.status === 'completed' && (
                      <span className="badge-completed px-2 py-1 rounded-pill small fw-semibold" style={{ backgroundColor: 'rgba(78, 184, 73, 0.08)', color: '#4EB849' }}>
                        &bull; Completed
                      </span>
                    )}
                    {sub.status === 'processing' && (
                      <span className="badge-processing px-2 py-1 rounded-pill small fw-semibold" style={{ backgroundColor: 'rgba(245, 158, 11, 0.08)', color: '#f59e0b' }}>
                        &bull; Processing
                      </span>
                    )}
                    {sub.status === 'pending' && (
                      <span className="badge-pending px-2 py-1 rounded-pill small fw-semibold" style={{ backgroundColor: 'rgba(239, 68, 68, 0.08)', color: '#ef4444' }}>
                        &bull; Pending
                      </span>
                    )}
                  </td>
                  <td>
                    <span className={`text-uppercase small fw-bold ${sub.priority === 'urgent' || sub.priority === 'high' ? 'text-danger' : 'text-muted'}`}>
                      {sub.priority}
                    </span>
                  </td>
                  <td className="small text-muted">
                    {sub.updatedAt ? new Date(sub.updatedAt.seconds * 1000).toLocaleDateString('en-GB') : ''}
                  </td>
                  <td className="text-end">
                    <button
                      className="btn btn-sm btn-light border-0 shadow-xs"
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
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
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

                  <div className="row g-3">
                    <div className="col-md-6">
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

                    <div className="col-md-6">
                      <label className="form-label small fw-bold text-muted">Attachments</label>
                      <input 
                        type="file" 
                        className="form-control bg-light border-0" 
                        multiple 
                        onChange={(e) => setNewFiles(Array.from(e.target.files))}
                      />
                    </div>
                  </div>
                </div>
                <div className="modal-footer border-top-0 pt-0 px-4 pb-4">
                  <button type="button" className="btn btn-light fw-bold" onClick={() => setShowAddModal(false)}>Cancel</button>
                  <button type="submit" className="btn btn-primary fw-bold" style={{ backgroundColor: '#0C4DA2', borderColor: '#0C4DA2' }}>Upload & Submit</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}

      {/* DETAILS, CHAT & EDIT HISTORY MODAL */}
      {showDetailsModal && selectedSub && (
        <div className="modal show d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog modal-xl modal-dialog-centered">
            <div className="modal-content rounded-4 border-0">
              <div className="modal-header border-bottom-0 pt-4 px-4">
                <h5 className="modal-title fw-bold text-dark">Submission Details Desk</h5>
                <button type="button" className="btn-close" onClick={() => setShowDetailsModal(false)}></button>
              </div>
              <div className="modal-body px-4 pb-4">
                <div className="row g-4">
                  {/* Left Column: Details & Edit */}
                  <div className="col-lg-6">
                    <div className="card bg-light border-0 rounded-4 p-4 mb-3">
                      <h6 className="fw-bold mb-3 text-primary" style={{ color: '#0C4DA2' }}>Work Parameters</h6>
                      
                      {currentUser.role === 'college_user' && selectedSub.status !== 'pending' ? (
                        /* Read Only View for Non-Pending status */
                        <div>
                          <div className="mb-2"><strong>Title:</strong> {selectedSub.title}</div>
                          <div className="mb-2"><strong>Description:</strong> {selectedSub.description}</div>
                          <div className="mb-2"><strong>Priority:</strong> <span className="text-uppercase fw-bold text-muted">{selectedSub.priority}</span></div>
                          <div className="alert alert-warning border-0 rounded-3 small mt-3">
                            <i className="bi bi-lock-fill me-2"></i>Submissions in **{selectedSub.status}** status are locked and cannot be edited.
                          </div>
                        </div>
                      ) : currentUser.role === 'admin' ? (
                        /* Admin View Only */
                        <div>
                          <div className="mb-2"><strong>Title:</strong> {selectedSub.title}</div>
                          <div className="mb-2"><strong>Description:</strong> {selectedSub.description}</div>
                          <div className="mb-2"><strong>Priority:</strong> <span className="text-uppercase fw-bold text-muted">{selectedSub.priority}</span></div>
                          <div className="alert alert-info border-0 rounded-3 small mt-3">
                            <i className="bi bi-info-circle-fill me-2"></i>Central Administrators have view-only access.
                          </div>
                        </div>
                      ) : (
                        /* Edit Form for College (Pending status) or Super Admin */
                        <form onSubmit={handleEditSubmit}>
                          <div className="mb-3">
                            <label className="form-label small fw-bold text-muted">Submission Title</label>
                            <input 
                              type="text" 
                              className="form-control border-0 bg-white" 
                              value={editTitle}
                              onChange={(e) => setEditTitle(e.target.value)}
                              required
                            />
                          </div>
                          <div className="mb-3">
                            <label className="form-label small fw-bold text-muted">Description</label>
                            <textarea 
                              className="form-control border-0 bg-white" 
                              rows="3"
                              value={editDescription}
                              onChange={(e) => setEditDescription(e.target.value)}
                            ></textarea>
                          </div>
                          <div className="mb-3">
                            <label className="form-label small fw-bold text-muted">Priority</label>
                            <select 
                              className="form-select border-0 bg-white"
                              value={editPriority}
                              onChange={(e) => setEditPriority(e.target.value)}
                            >
                              <option value="low">Low</option>
                              <option value="normal">Normal</option>
                              <option value="high">High</option>
                              <option value="urgent">Urgent</option>
                            </select>
                          </div>
                          <button type="submit" className="btn btn-sm btn-primary fw-bold px-3 py-2 border-0" style={{ backgroundColor: '#4EB849' }}>
                            Save Updates
                          </button>
                        </form>
                      )}
                    </div>

                    {/* Admin Status Management Action Desk */}
                    {currentUser.role === 'super_admin' && (
                      <div className="card bg-light border-0 rounded-4 p-4">
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
                          <button className="btn btn-warning text-white fw-bold btn-sm" onClick={() => handleUpdateStatus('processing')}>Set Processing</button>
                          <button className="btn btn-success fw-bold btn-sm" onClick={() => handleUpdateStatus('completed')}>Set Completed</button>
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Right Column: Chat Timeline & Super Admin History */}
                  <div className="col-lg-6">
                    {/* Live Communications Chat */}
                    <div className="card border-0 rounded-4 shadow-sm mb-3">
                      <div className="card-header bg-light border-0 py-3 px-4">
                        <h6 className="fw-bold mb-0 text-dark">Communication Channel</h6>
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
                            className="form-control bg-light border-0" 
                            value={chatText}
                            onChange={(e) => setChatText(e.target.value)}
                            placeholder="Type a message..."
                            style={{ borderRadius: '10px 0 0 10px', padding: '0.6rem' }}
                          />
                          <button className="btn btn-primary border-0 px-4" type="submit" style={{ backgroundColor: '#0C4DA2', borderRadius: '0 10px 10px 0' }}>
                            Send
                          </button>
                        </form>
                      </div>
                    </div>

                    {/* Super Admin Edit History Log Timeline */}
                    {currentUser.role === 'super_admin' && (
                      <div className="card border-0 rounded-4 shadow-sm">
                        <div className="card-header bg-light border-0 py-3 px-4">
                          <h6 className="fw-bold mb-0 text-dark">College Re-update Timeline ({selectedSub.editCount || 0})</h6>
                        </div>
                        <div className="card-body p-4" style={{ maxHeight: '180px', overflowY: 'auto' }}>
                          {editHistory.length === 0 ? (
                            <span className="small text-muted">No edit updates recorded.</span>
                          ) : (
                            <div className="timeline-container">
                              {editHistory.map((hist) => (
                                <div key={hist.id} className="border-left pb-3 ps-3 position-relative">
                                  <div className="bullet-indicator bg-primary position-absolute rounded-circle" style={{ left: '-5px', top: '5px', width: '10px', height: '10px', backgroundColor: '#4EB849' }}></div>
                                  <div className="small fw-bold text-dark">{hist.editedByName} updated:</div>
                                  <div className="small text-muted mt-1">&quot;{hist.title}&quot; &bull; {hist.priority}</div>
                                  <div className="small text-muted-opacity" style={{ fontSize: '0.7rem' }}>
                                    {hist.editedAt ? new Date(hist.editedAt.seconds * 1000).toLocaleString('en-GB') : ''}
                                  </div>
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>
                    )}

                  </div>
                </div>
              </div>
              <div className="modal-footer border-top-0 pt-0 px-4 pb-4">
                <button type="button" className="btn btn-light fw-bold" onClick={() => setShowDetailsModal(false)}>Close Desk</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
