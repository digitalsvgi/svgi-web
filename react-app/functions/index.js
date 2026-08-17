const functions = require('firebase-functions');
const admin = require('firebase-admin');
const { google } = require('googleapis');
const Busboy = require('busboy');
const path = require('path');
const os = require('os');
const fs = require('fs');

admin.initializeApp();

// Load Google Drive parameters from environment variables
const CLIENT_ID = process.env.GD_CLIENT_ID || functions.config().gd.client_id;
const CLIENT_SECRET = process.env.GD_CLIENT_SECRET || functions.config().gd.client_secret;
const REFRESH_TOKEN = process.env.GD_REFRESH_TOKEN || functions.config().gd.refresh_token;
const REDIRECT_URI = process.env.GD_REDIRECT_URI || functions.config().gd.redirect_uri || 'https://developers.google.com/oauthplayground';
const PARENT_FOLDER_ID = process.env.GD_PARENT_FOLDER_ID || functions.config().gd.parent_folder_id;

// Setup OAuth2 client helper
const oauth2Client = new google.auth.OAuth2(CLIENT_ID, CLIENT_SECRET, REDIRECT_URI);
oauth2Client.setCredentials({ refresh_token: REFRESH_TOKEN });
const drive = google.drive({ version: 'v3', auth: oauth2Client });

/**
 * Finds or creates a folder inside Google Drive
 */
async function getOrCreateFolder(name, parentId = null) {
  let query = `name = '${name.replace(/'/g, "\\'")}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false`;
  if (parentId) {
    query += ` and '${parentId}' in parents`;
  }

  const res = await drive.files.list({
    q: query,
    spaces: 'drive',
    fields: 'files(id, name)',
    pageSize: 1
  });

  const files = res.data.files;
  if (files && files.length > 0) {
    return files[0].id;
  }

  // Create folder if not exists
  const fileMetadata = {
    name: name,
    mimeType: 'application/vnd.google-apps.folder'
  };
  if (parentId) {
    fileMetadata.parents = [parentId];
  }

  const folder = await drive.files.create({
    resource: fileMetadata,
    fields: 'id'
  });

  // Make folder public (Read-Only) matching SVGI portal rules
  await drive.permissions.create({
    fileId: folder.data.id,
    resource: {
      role: 'reader',
      type: 'anyone'
    }
  });

  return folder.data.id;
}

/**
 * Cloud Function to handle document/image upload to Google Drive
 */
exports.uploadToDrive = functions.https.onRequest(async (req, res) => {
  // Handle CORS
  res.set('Access-Control-Allow-Origin', '*');
  if (req.method === 'OPTIONS') {
    res.set('Access-Control-Allow-Methods', 'POST');
    res.set('Access-Control-Allow-Headers', 'Content-Type');
    res.status(204).send('');
    return;
  }

  if (req.method !== 'POST') {
    res.status(405).send('Method Not Allowed');
    return;
  }

  const busboy = Busboy({ headers: req.headers });
  const tmpdir = os.tmpdir();
  const uploads = {};
  const fields = {};

  busboy.on('field', (fieldname, val) => {
    fields[fieldname] = val;
  });

  busboy.on('file', (fieldname, file, info) => {
    const { filename, mimeType } = info;
    const filepath = path.join(tmpdir, filename);
    uploads[fieldname] = { filepath, filename, mimeType };
    
    const writeStream = fs.createWriteStream(filepath);
    file.pipe(writeStream);
  });

  busboy.on('finish', async () => {
    try {
      const collegeName = fields.collegeName || 'Default College';
      const deptName = fields.departmentName || 'Default Department';

      // 1. Resolve date-based Google Drive subfolder structure
      const rootFolder = PARENT_FOLDER_ID || await getOrCreateFolder('College Management System');
      const collegeFolder = await getOrCreateFolder(collegeName, rootFolder);
      const deptFolder = await getOrCreateFolder(deptName, collegeFolder);

      const today = new Date();
      const dateStr = `${String(today.getDate()).padStart(2, '0')}-${String(today.getMonth() + 1).padStart(2, '0')}-${today.getFullYear()}`;
      const targetFolderId = await getOrCreateFolder(dateStr, deptFolder);

      const results = [];

      // 2. Upload files
      for (const key of Object.keys(uploads)) {
        const fileObj = uploads[key];
        
        const fileMetadata = {
          name: fileObj.filename,
          parents: [targetFolderId]
        };

        const media = {
          mimeType: fileObj.mimeType,
          body: fs.createReadStream(fileObj.filepath)
        };

        const driveFile = await drive.files.create({
          resource: fileMetadata,
          media: media,
          fields: 'id, webViewLink'
        });

        // Make file public
        await drive.permissions.create({
          fileId: driveFile.data.id,
          resource: {
            role: 'reader',
            type: 'anyone'
          }
        });

        results.push({
          name: fileObj.filename,
          googleDriveFileId: driveFile.data.id,
          googleDriveUrl: driveFile.data.webViewLink
        });

        // Delete temporary file
        fs.unlinkSync(fileObj.filepath);
      }

      res.status(200).json({ success: true, files: results, folderId: targetFolderId });
    } catch (error) {
      console.error("Upload error in Cloud Functions:", error);
      res.status(500).json({ success: false, message: error.message });
    }
  });

  busboy.end(req.rawBody);
});

/**
 * Cloud Function to delete a file from Google Drive
 */
exports.deleteFromDrive = functions.https.onRequest(async (req, res) => {
  res.set('Access-Control-Allow-Origin', '*');
  if (req.method === 'OPTIONS') {
    res.set('Access-Control-Allow-Methods', 'POST, DELETE');
    res.set('Access-Control-Allow-Headers', 'Content-Type');
    res.status(204).send('');
    return;
  }

  if (req.method !== 'POST' && req.method !== 'DELETE') {
    res.status(405).send('Method Not Allowed');
    return;
  }

  try {
    const { fileId } = req.body;
    if (!fileId) {
      res.status(400).json({ success: false, message: 'Missing fileId' });
      return;
    }

    await drive.files.delete({ fileId: fileId });
    res.status(200).json({ success: true, message: 'File deleted successfully.' });
  } catch (error) {
    console.error("Delete error in Cloud Functions:", error);
    res.status(500).json({ success: false, message: error.message });
  }
});

/**
 * Cloud Function to create/sync auth users and Firestore user profiles
 */
exports.createSystemUser = functions.https.onRequest(async (req, res) => {
  res.set('Access-Control-Allow-Origin', '*');
  if (req.method === 'OPTIONS') {
    res.set('Access-Control-Allow-Methods', 'POST');
    res.set('Access-Control-Allow-Headers', 'Content-Type');
    res.status(204).send('');
    return;
  }

  if (req.method !== 'POST') {
    res.status(405).send('Method Not Allowed');
    return;
  }

  try {
    const { email, password, name, role, collegeId } = req.body;

    if (!email || !password || !name || !role) {
      res.status(400).json({ success: false, message: 'Missing required parameters.' });
      return;
    }

    // 1. Create/Update user in Firebase Authentication
    let userRecord;
    try {
      userRecord = await admin.auth().getUserByEmail(email);
      await admin.auth().updateUser(userRecord.uid, {
        password: password,
        displayName: name
      });
    } catch (authError) {
      if (authError.code === 'auth/user-not-found') {
        userRecord = await admin.auth().createUser({
          email: email,
          password: password,
          displayName: name
        });
      } else {
        throw authError;
      }
    }

    // 2. Set role profile metadata inside Firestore '/users' collection
    await admin.firestore().collection('users').doc(userRecord.uid).set({
      name: name,
      email: email,
      role: role,
      collegeId: collegeId || null,
      status: 'active'
    });

    res.status(200).json({ success: true, uid: userRecord.uid });
  } catch (error) {
    console.error("User creation error in Cloud Functions:", error);
    res.status(500).json({ success: false, message: error.message });
  }
});

