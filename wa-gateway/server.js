const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');
const QRCode = require('qrcode');
const pino = require('pino');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore,
    downloadMediaMessage,
    downloadContentFromMessage,
    proto
} = require('@whiskeysockets/baileys');

const app = express();
const PORT = process.env.PORT || 3000;
// Gateway tidak memiliki autentikasi; hanya boleh diakses dari server yang sama (Laravel
// memanggil http://127.0.0.1:3000). Jangan ubah HOST ke 0.0.0.0 tanpa firewall/reverse proxy.
const HOST = process.env.HOST || '127.0.0.1';

// Tidak ada pemanggilan dari browser: tolak permintaan lintas origin.
app.use(cors({ origin: false }));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

const logger = pino({ level: 'error' });
const SESSIONS_DIR = path.join(__dirname, 'sessions');

if (!fs.existsSync(SESSIONS_DIR)) {
    fs.mkdirSync(SESSIONS_DIR, { recursive: true });
}

// In-Memory Storage untuk Sesi Aktif
// Map<sessionId, sessionData>
const sessions = new Map();

/**
 * Format nomor telepon ke WhatsApp JID (628xxx@s.whatsapp.net)
 */
function formatToJid(number) {
    if (!number) return null;
    let clean = number.toString().replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) {
        clean = '62' + clean.slice(1);
    }
    if (!clean.endsWith('@s.whatsapp.net') && !clean.endsWith('@g.us')) {
        clean = `${clean}@s.whatsapp.net`;
    }
    return clean;
}

/**
 * Konversi JID ke nomor telepon yang mudah dibaca
 */
function jidToNumber(jid) {
    if (!jid) return '';
    return jid.replace('@s.whatsapp.net', '').replace('@g.us', '').replace('@lid', '');
}

/**
 * Deteksi ekstensi file berdasarkan tipe mime
 */
function getExtensionFromMime(mime, filename) {
    if (filename && filename.includes('.')) {
        return filename.split('.').pop().toLowerCase();
    }
    if (!mime) return 'bin';
    if (mime.includes('pdf')) return 'pdf';
    if (mime.includes('jpeg') || mime.includes('jpg')) return 'jpg';
    if (mime.includes('png')) return 'png';
    if (mime.includes('webp')) return 'webp';
    if (mime.includes('mp4')) return 'mp4';
    if (mime.includes('ogg') || mime.includes('opus')) return 'ogg';
    if (mime.includes('mp3') || mime.includes('mpeg')) return 'mp3';
    if (mime.includes('word') || mime.includes('docx')) return 'docx';
    if (mime.includes('excel') || mime.includes('xlsx')) return 'xlsx';
    if (mime.includes('zip')) return 'zip';
    return 'bin';
}

/**
 * Ekstraksi informasi teks dan berkas media dari pesan Baileys
 */
function extractMessageInfo(msg) {
    if (!msg || !msg.message) return { body: '', media: null };

    const m = msg.message;
    // Buka pembungkus jika pesan bertipe ephemeral atau viewOnce
    const real = m.ephemeralMessage?.message || m.viewOnceMessage?.message || m.viewOnceMessageV2?.message || m;

    let body = '';
    let media = null;

    if (real.conversation) {
        body = real.conversation;
    } else if (real.extendedTextMessage?.text) {
        body = real.extendedTextMessage.text;
    } else if (real.imageMessage) {
        body = real.imageMessage.caption || '';
        media = {
            type: 'image',
            mimetype: real.imageMessage.mimetype || 'image/jpeg',
            filename: `foto_${msg.key.id}.jpg`,
            fileSize: real.imageMessage.fileLength || 0,
            caption: body
        };
    } else if (real.videoMessage) {
        body = real.videoMessage.caption || '';
        media = {
            type: 'video',
            mimetype: real.videoMessage.mimetype || 'video/mp4',
            filename: `video_${msg.key.id}.mp4`,
            fileSize: real.videoMessage.fileLength || 0,
            seconds: real.videoMessage.seconds || 0,
            caption: body
        };
    } else if (real.documentMessage) {
        body = real.documentMessage.caption || '';
        const fn = real.documentMessage.fileName || `dokumen_${msg.key.id}.pdf`;
        media = {
            type: 'document',
            mimetype: real.documentMessage.mimetype || 'application/pdf',
            filename: fn,
            fileSize: real.documentMessage.fileLength || 0,
            caption: body
        };
    } else if (real.audioMessage) {
        body = '';
        media = {
            type: 'audio',
            mimetype: real.audioMessage.mimetype || 'audio/ogg',
            filename: `audio_${msg.key.id}.ogg`,
            fileSize: real.audioMessage.fileLength || 0,
            isVoiceNote: Boolean(real.audioMessage.ptt),
            seconds: real.audioMessage.seconds || 0
        };
    } else if (real.stickerMessage) {
        media = {
            type: 'sticker',
            mimetype: 'image/webp',
            filename: `sticker_${msg.key.id}.webp`,
            fileSize: real.stickerMessage.fileLength || 0
        };
    }

    return { body, media };
}

/**
 * Unduh buffer media menggunakan Baileys
 */
async function downloadMessageBuffer(rawMsg, sock) {
    if (!rawMsg || !rawMsg.message) return null;

    try {
        if (typeof downloadMediaMessage === 'function') {
            const buf = await downloadMediaMessage(
                rawMsg,
                'buffer',
                {},
                {
                    logger,
                    reuploadRequest: sock?.updateMediaMessage
                }
            );
            if (buf && buf.length > 0) return buf;
        }
    } catch (e) {
        // Lanjutkan ke metode alternatif
    }

    // Metode alternatif menggunakan downloadContentFromMessage
    try {
        const m = rawMsg.message?.ephemeralMessage?.message || rawMsg.message?.viewOnceMessage?.message || rawMsg.message;
        let type = null;
        let msgObj = null;

        if (m.imageMessage) { type = 'image'; msgObj = m.imageMessage; }
        else if (m.videoMessage) { type = 'video'; msgObj = m.videoMessage; }
        else if (m.documentMessage) { type = 'document'; msgObj = m.documentMessage; }
        else if (m.audioMessage) { type = 'audio'; msgObj = m.audioMessage; }
        else if (m.stickerMessage) { type = 'sticker'; msgObj = m.stickerMessage; }

        if (!type || !msgObj) return null;

        const stream = await downloadContentFromMessage(msgObj, type);
        let buffer = Buffer.from([]);
        for await (const chunk of stream) {
            buffer = Buffer.concat([buffer, chunk]);
        }
        return buffer;
    } catch (err) {
        console.error('Gagal mengunduh buffer media:', err.message);
        return null;
    }
}

/**
 * Simpan file media ke penyimpanan lokal server
 */
async function downloadAndSaveMedia(sessionFolder, rawMsg, sock) {
    if (!rawMsg || !rawMsg.message) return null;

    try {
        const msgId = rawMsg.key.id;
        const mediaDir = path.join(sessionFolder, 'media');
        if (!fs.existsSync(mediaDir)) {
            fs.mkdirSync(mediaDir, { recursive: true });
        }

        const info = extractMessageInfo(rawMsg);
        if (!info.media) return null;

        const ext = getExtensionFromMime(info.media.mimetype, info.media.filename);
        const fileName = `${msgId}.${ext}`;
        const savePath = path.join(mediaDir, fileName);

        if (fs.existsSync(savePath)) {
            return savePath;
        }

        const buffer = await downloadMessageBuffer(rawMsg, sock);
        if (buffer && buffer.length > 0) {
            fs.writeFileSync(savePath, buffer);
            console.log(`Media tersimpan: ${fileName} (${buffer.length} bytes)`);
            return savePath;
        }
    } catch (err) {
        console.error('Gagal menyimpan file media:', err.message);
    }
    return null;
}

/**
 * Memuat data riwayat percakapan dari disk penyimpanan lokal
 */
function loadSessionStore(sessionFolder, sessionData) {
    try {
        const storeFile = path.join(sessionFolder, 'store.json');
        if (fs.existsSync(storeFile)) {
            const raw = fs.readFileSync(storeFile, 'utf8');
            const data = JSON.parse(raw);

            if (Array.isArray(data.chats)) {
                for (const c of data.chats) {
                    sessionData.chats.set(c.jid, c);
                }
            }
            if (data.messages && typeof data.messages === 'object') {
                for (const [jid, msgs] of Object.entries(data.messages)) {
                    sessionData.messages.set(jid, msgs);
                }
            }
            if (Array.isArray(data.calls)) {
                sessionData.calls = data.calls;
            }
            if (data.contacts && typeof data.contacts === 'object') {
                for (const [jid, name] of Object.entries(data.contacts)) {
                    sessionData.contacts.set(jid, name);
                }
            }
            console.log(`[${sessionData.sessionId}] Data tersimpan dimuat: ${sessionData.chats.size} percakapan, ${sessionData.calls.length} riwayat panggilan`);
        }
    } catch (err) {
        console.error(`[${sessionData.sessionId}] Gagal memuat store.json:`, err.message);
    }
}

/**
 * Menyimpan data riwayat percakapan ke disk lokal secara berkala
 */
const saveTimers = new Map();
function saveSessionStore(sessionId) {
    if (saveTimers.has(sessionId)) clearTimeout(saveTimers.get(sessionId));
    saveTimers.set(sessionId, setTimeout(() => {
        const session = sessions.get(sessionId);
        if (!session) return;
        const sessionFolder = session.customFolder || path.join(SESSIONS_DIR, sessionId);
        if (!fs.existsSync(sessionFolder)) return;

        try {
            const storeFile = path.join(sessionFolder, 'store.json');
            const data = {
                chats: Array.from(session.chats.values()),
                messages: Object.fromEntries(session.messages.entries()),
                calls: session.calls || [],
                contacts: Object.fromEntries(session.contacts.entries())
            };
            fs.writeFileSync(storeFile, JSON.stringify(data, null, 2), 'utf8');
        } catch (e) {
            console.error(`[${sessionId}] Gagal menyimpan store.json:`, e.message);
        }
    }, 1500));
}

/**
 * Inisialisasi atau hubungkan sesi WhatsApp
 */
async function createSession(sessionId, label = '', customFolder = null) {
    if (sessions.has(sessionId)) {
        const existing = sessions.get(sessionId);
        if (existing.status === 'CONNECTED' && existing.sock) {
            return existing;
        }
    }

    const sessionFolder = customFolder || path.join(SESSIONS_DIR, sessionId);
    if (!fs.existsSync(sessionFolder)) {
        fs.mkdirSync(sessionFolder, { recursive: true });
    }

    const mediaDir = path.join(sessionFolder, 'media');
    if (!fs.existsSync(mediaDir)) {
        fs.mkdirSync(mediaDir, { recursive: true });
    }

    const { state, saveCreds } = await useMultiFileAuthState(sessionFolder);
    const { version } = await fetchLatestBaileysVersion();

    const sessionData = {
        sessionId,
        label: label || sessionId,
        customFolder: sessionFolder,
        sock: null,
        status: 'CONNECTING',
        qr: null,
        phone: null,
        name: null,
        chats: new Map(),
        messages: new Map(),
        calls: [],
        contacts: new Map(),
        groupMetadata: new Map(),
        rawMessages: new Map(),
    };

    // Muat data percakapan sebelumnya yang tersimpan di disk
    loadSessionStore(sessionFolder, sessionData);

    sessions.set(sessionId, sessionData);

    const sock = makeWASocket({
        version,
        logger,
        printQRInTerminal: false,
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, logger),
        },
        browser: ['SINDEN Desktop', 'Chrome', '128.0.0.0'],
        syncFullHistory: true,
        connectTimeoutMs: 60000,
        keepAliveIntervalMs: 30000,
        getMessage: async (key) => {
            if (sessionData.messages.has(key.remoteJid)) {
                const list = sessionData.messages.get(key.remoteJid);
                const found = list.find(m => m.id === key.id);
                if (found) return { conversation: found.body };
            }
            return undefined;
        }
    });

    sessionData.sock = sock;

    // Simpan kredensial jika diperbarui
    sock.ev.on('creds.update', saveCreds);

    // Pembaruan status sambungan
    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            try {
                sessionData.qr = await QRCode.toDataURL(qr, { margin: 2, scale: 8 });
                sessionData.status = 'QR_READY';
            } catch (err) {
                console.error(`[${sessionId}] QR generation error:`, err);
            }
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
            console.log(`[${sessionId}] Koneksi ditutup: ${statusCode}, Reconnect: ${shouldReconnect}`);

            if (statusCode === DisconnectReason.loggedOut) {
                sessionData.status = 'DISCONNECTED';
                sessionData.qr = null;
                sessionData.phone = null;
                sessionData.name = null;
                try {
                    if (fs.existsSync(sessionFolder)) {
                        fs.rmSync(sessionFolder, { recursive: true, force: true });
                    }
                } catch (e) {
                    console.error(`[${sessionId}] Gagal menghapus folder sesi:`, e);
                }
                // Sesi utama dibuat ulang agar Super Admin langsung mendapat QR baru.
                if (sessionId === DEFAULT_SESSION_ID) {
                    sessions.delete(sessionId);
                    setTimeout(() => ensureDefaultSession(), 3000);
                }
            } else if (shouldReconnect) {
                sessionData.status = 'CONNECTING';
                setTimeout(() => {
                    createSession(sessionId, sessionData.label, customFolder).catch(console.error);
                }, 3000);
            } else {
                sessionData.status = 'DISCONNECTED';
            }
        } else if (connection === 'open') {
            sessionData.status = 'CONNECTED';
            sessionData.qr = null;
            const userJid = sock.user?.id || '';
            sessionData.phone = jidToNumber(userJid.split(':')[0] || userJid);
            sessionData.name = sock.user?.name || sessionData.phone;
            console.log(`[${sessionId}] Berhasil terhubung: ${sessionData.phone} (${sessionData.name})`);
            saveSessionStore(sessionId);
        }
    });

    // Kontak diperbarui atau diterima
    sock.ev.on('contacts.upsert', (newContacts) => {
        if (!Array.isArray(newContacts)) return;
        for (const c of newContacts) {
            if (c.id) {
                const displayName = c.name || c.notify || c.verifiedName;
                if (displayName) {
                    sessionData.contacts.set(c.id, displayName);
                    if (sessionData.chats.has(c.id)) {
                        const ch = sessionData.chats.get(c.id);
                        if (!ch.isGroup && (!ch.name || ch.name.includes('@lid') || ch.name.startsWith('62'))) {
                            ch.name = displayName;
                        }
                    }
                }
            }
        }
        saveSessionStore(sessionId);
    });

    sock.ev.on('contacts.set', ({ contacts: setContacts }) => {
        if (!Array.isArray(setContacts)) return;
        for (const c of setContacts) {
            if (c.id) {
                const displayName = c.name || c.notify || c.verifiedName;
                if (displayName) {
                    sessionData.contacts.set(c.id, displayName);
                }
            }
        }
        saveSessionStore(sessionId);
    });

    // Sinkronisasi riwayat pesan awal dari WhatsApp Multi-Device
    sock.ev.on('messaging-history.set', async ({ chats: historyChats, contacts: historyContacts, messages: historyMessages }) => {
        console.log(`[${sessionId}] Sinkronisasi riwayat diterima: ${historyChats?.length || 0} percakapan, ${historyMessages?.length || 0} pesan, ${historyContacts?.length || 0} kontak`);

        if (Array.isArray(historyContacts)) {
            for (const c of historyContacts) {
                if (c.id) {
                    sessionData.contacts.set(c.id, c.name || c.notify || c.verifiedName || '');
                }
            }
        }

        if (Array.isArray(historyMessages)) {
            for (const msg of historyMessages) {
                if (!msg.message) continue;
                const jid = msg.key.remoteJid;
                if (!jid || jid.endsWith('@broadcast')) continue;

                sessionData.rawMessages.set(msg.key.id, msg);

                const isGroup = jid.endsWith('@g.us');
                const fromMe = Boolean(msg.key.fromMe);
                const info = extractMessageInfo(msg);
                const timestamp = msg.messageTimestamp ? new Date(msg.messageTimestamp * 1000).toISOString() : new Date().toISOString();
                const participantJid = msg.key.participant || msg.participant || '';
                const participantNumber = participantJid ? jidToNumber(participantJid) : '';

                if (!sessionData.messages.has(jid)) {
                    sessionData.messages.set(jid, []);
                }
                const list = sessionData.messages.get(jid);
                if (!list.some(x => x.id === msg.key.id)) {
                    list.push({
                        id: msg.key.id,
                        fromMe,
                        isGroup,
                        participant: participantNumber,
                        participantName: isGroup && !fromMe ? (msg.pushName || sessionData.contacts.get(participantJid) || participantNumber) : '',
                        body: info.body || (info.media ? (info.media.type === 'document' ? info.media.filename : `[${info.media.type.toUpperCase()}]`) : ''),
                        media: info.media,
                        timestamp,
                        status: fromMe ? 'SENT' : 'RECEIVED'
                    });
                }

                // Unduh berkas media di latar belakang jika ada
                if (info.media) {
                    downloadAndSaveMedia(sessionFolder, msg, sock).catch(() => {});
                }
            }
        }

        if (Array.isArray(historyChats)) {
            for (const c of historyChats) {
                const jid = c.id;
                if (!jid || jid.endsWith('@broadcast')) continue;

                const isGroup = jid.endsWith('@g.us');
                const chatMessages = sessionData.messages.get(jid) || [];
                const lastMsg = chatMessages[chatMessages.length - 1];

                const contactName = sessionData.contacts.get(jid);
                let chatName = c.name || contactName;
                if (!chatName) {
                    chatName = isGroup ? 'Grup WhatsApp' : (jid.includes('@lid') ? 'Kontak WhatsApp' : jidToNumber(jid));
                }

                sessionData.chats.set(jid, {
                    jid,
                    isGroup,
                    number: isGroup ? '' : jidToNumber(jid),
                    name: chatName,
                    lastMessage: lastMsg?.body || (lastMsg?.media ? lastMsg.media.filename : ''),
                    lastSender: lastMsg?.participantName || '',
                    timestamp: lastMsg?.timestamp || (c.conversationTimestamp ? new Date(c.conversationTimestamp * 1000).toISOString() : new Date().toISOString()),
                    unreadCount: c.unreadCount || 0,
                });
            }
        }

        saveSessionStore(sessionId);
    });

    // Percakapan baru / diperbarui
    sock.ev.on('chats.upsert', (newChats) => {
        if (!Array.isArray(newChats)) return;
        for (const c of newChats) {
            const jid = c.id;
            if (!jid || jid.endsWith('@broadcast')) continue;
            const isGroup = jid.endsWith('@g.us');
            const existing = sessionData.chats.get(jid);
            const contactName = sessionData.contacts.get(jid);
            let chatName = c.name || contactName || existing?.name;
            if (!chatName) {
                chatName = isGroup ? 'Grup WhatsApp' : (jid.includes('@lid') ? 'Kontak WhatsApp' : jidToNumber(jid));
            }

            sessionData.chats.set(jid, {
                jid,
                isGroup,
                number: isGroup ? '' : jidToNumber(jid),
                name: chatName,
                lastMessage: existing?.lastMessage || '',
                lastSender: existing?.lastSender || '',
                timestamp: c.conversationTimestamp ? new Date(c.conversationTimestamp * 1000).toISOString() : (existing?.timestamp || new Date().toISOString()),
                unreadCount: c.unreadCount ?? (existing?.unreadCount || 0),
            });
        }
        saveSessionStore(sessionId);
    });

    sock.ev.on('chats.update', (updates) => {
        if (!Array.isArray(updates)) return;
        for (const u of updates) {
            const jid = u.id;
            if (sessionData.chats.has(jid)) {
                const chat = sessionData.chats.get(jid);
                if (u.unreadCount !== undefined) chat.unreadCount = u.unreadCount;
                if (u.name) chat.name = u.name;
            }
        }
        saveSessionStore(sessionId);
    });

    // Panggilan masuk / telepon
    sock.ev.on('call', async (callEvents) => {
        if (!Array.isArray(callEvents)) return;
        for (const c of callEvents) {
            const fromJid = c.from || c.chatId || '';
            const fromNumber = jidToNumber(fromJid);
            const callItem = {
                id: c.id,
                from: fromJid,
                number: fromNumber,
                name: sessionData.contacts.get(fromJid) || fromNumber,
                isVideo: Boolean(c.isVideo),
                isGroup: Boolean(c.isGroup),
                status: c.status || 'ringing',
                timestamp: new Date(c.date || Date.now()).toISOString(),
            };

            const existingIdx = sessionData.calls.findIndex(x => x.id === c.id);
            if (existingIdx >= 0) {
                sessionData.calls[existingIdx] = { ...sessionData.calls[existingIdx], ...callItem };
            } else {
                sessionData.calls.unshift(callItem);
            }

            if (sessionData.calls.length > 50) {
                sessionData.calls.pop();
            }
        }
        saveSessionStore(sessionId);
    });

    // Pesan baru masuk dan keluar
    sock.ev.on('messages.upsert', async (m) => {
        if (!m.messages || m.messages.length === 0) return;

        for (const msg of m.messages) {
            if (!msg.message) continue;
            const jid = msg.key.remoteJid;
            if (!jid || jid.endsWith('@broadcast')) continue;

            // Simpan pesan mentah untuk kebutuhan pengunduhan media on-demand
            sessionData.rawMessages.set(msg.key.id, msg);
            if (sessionData.rawMessages.size > 250) {
                const firstKey = sessionData.rawMessages.keys().next().value;
                sessionData.rawMessages.delete(firstKey);
            }

            const isGroup = jid.endsWith('@g.us');
            const fromMe = Boolean(msg.key.fromMe);
            const info = extractMessageInfo(msg);
            const timestamp = msg.messageTimestamp ? new Date(msg.messageTimestamp * 1000).toISOString() : new Date().toISOString();

            const participantJid = msg.key.participant || msg.participant || '';
            const participantNumber = participantJid ? jidToNumber(participantJid) : '';
            const senderName = msg.pushName || (isGroup ? participantNumber : (sessionData.contacts.get(jid) || jidToNumber(jid)));

            // Jika grup dan belum ada subjek nama grup, tarik metadata grup
            if (isGroup && !sessionData.groupMetadata.has(jid)) {
                sock.groupMetadata(jid).then(meta => {
                    sessionData.groupMetadata.set(jid, meta);
                    if (sessionData.chats.has(jid)) {
                        sessionData.chats.get(jid).name = meta.subject || sessionData.chats.get(jid).name;
                    }
                }).catch(() => {});
            }

            const groupSubject = sessionData.groupMetadata.get(jid)?.subject;
            let chatDisplayName = isGroup ? (groupSubject || 'Grup WhatsApp') : (sessionData.contacts.get(jid) || senderName || jidToNumber(jid));
            if (chatDisplayName.includes('@lid')) {
                chatDisplayName = sessionData.contacts.get(jid) || 'Kontak WhatsApp';
            }

            const displaySnippet = info.body || (info.media ? (info.media.type === 'document' ? info.media.filename : `[${info.media.type.toUpperCase()}]`) : '');

            // Perbarui daftar percakapan
            if (!sessionData.chats.has(jid)) {
                sessionData.chats.set(jid, {
                    jid,
                    isGroup,
                    number: isGroup ? '' : jidToNumber(jid),
                    name: chatDisplayName,
                    lastMessage: displaySnippet,
                    lastSender: isGroup ? (msg.pushName || participantNumber) : '',
                    timestamp,
                    unreadCount: fromMe ? 0 : 1,
                });
            } else {
                const existingChat = sessionData.chats.get(jid);
                existingChat.lastMessage = displaySnippet;
                existingChat.lastSender = isGroup ? (msg.pushName || participantNumber) : '';
                existingChat.timestamp = timestamp;
                if (!fromMe) {
                    existingChat.unreadCount = (existingChat.unreadCount || 0) + 1;
                }
                if (isGroup && groupSubject) {
                    existingChat.name = groupSubject;
                } else if (!isGroup && chatDisplayName && !chatDisplayName.includes('@lid')) {
                    existingChat.name = chatDisplayName;
                }
            }

            // Simpan riwayat pesan
            if (!sessionData.messages.has(jid)) {
                sessionData.messages.set(jid, []);
            }
            const chatMessages = sessionData.messages.get(jid);
            chatMessages.push({
                id: msg.key.id,
                fromMe,
                isGroup,
                participant: participantNumber,
                participantName: isGroup && !fromMe ? (msg.pushName || participantNumber) : '',
                body: info.body || (info.media ? (info.media.type === 'document' ? info.media.filename : `[${info.media.type.toUpperCase()}]`) : ''),
                media: info.media,
                timestamp,
                status: fromMe ? 'SENT' : 'RECEIVED'
            });

            if (chatMessages.length > 150) {
                chatMessages.shift();
            }

            // Unduh file media ke disk secara langsung saat pesan diterima
            if (info.media) {
                downloadAndSaveMedia(sessionFolder, msg, sock).catch(() => {});
            }
        }

        saveSessionStore(sessionId);
    });

    return sessionData;
}

/**
 * Sesi utama "dinas" dipakai Laravel (/send). Di instalasi baru atau setelah logout
 * belum ada sesi sama sekali, sehingga QR tidak pernah dibuat: buat otomatis.
 */
const DEFAULT_SESSION_ID = 'dinas';
let creatingDefault = false;
async function ensureDefaultSession() {
    const existing = sessions.get(DEFAULT_SESSION_ID);
    if (creatingDefault || (existing && existing.status !== 'DISCONNECTED')) return;
    creatingDefault = true;
    console.log(`Membuat sesi '${DEFAULT_SESSION_ID}' agar QR dapat dipindai...`);
    try {
        await createSession(DEFAULT_SESSION_ID, 'WA Dinas / Radar Utama');
    } catch (err) {
        console.error(err);
    } finally {
        creatingDefault = false;
    }
}

/**
 * Memulihkan sesi sebelumnya dari disk
 */
function restoreSessions() {
    const legacyPaths = [
        path.join(__dirname, 'auth_info_baileys'),
        path.join(__dirname, '..', 'auth_info_baileys'),
        path.join(process.cwd(), 'auth_info_baileys'),
    ];

    let legacyFound = false;
    for (const lPath of legacyPaths) {
        if (fs.existsSync(path.join(lPath, 'creds.json'))) {
            console.log(`Menemukan auth_info_baileys di ${lPath}, memulihkan sesi 'dinas'...`);
            createSession('dinas', 'WA Dinas / Radar Utama', lPath).catch(console.error);
            legacyFound = true;
            break;
        }
    }

    let defaultRestored = legacyFound;
    try {
        const dirs = fs.readdirSync(SESSIONS_DIR, { withFileTypes: true });
        for (const dir of dirs) {
            if (dir.isDirectory()) {
                const sessionId = dir.name;
                if (sessionId === 'dinas' && legacyFound) continue;
                const credsPath = path.join(SESSIONS_DIR, sessionId, 'creds.json');
                if (fs.existsSync(credsPath)) {
                    console.log(`Memulihkan sesi tersimpan: ${sessionId}`);
                    if (sessionId === DEFAULT_SESSION_ID) defaultRestored = true;
                    createSession(sessionId).catch(console.error);
                }
            }
        }
    } catch (err) {
        console.error('Gagal memulihkan sesi:', err);
    }
    return defaultRestored;
}

// -------------------------------------------------------------
// ENDPOINT REST API
// -------------------------------------------------------------

// 1. Status Sesi dan Gateway
app.get('/status', (req, res) => {
    const list = [];
    let legacyStatus = 'OFFLINE';
    let legacyQR = null;

    for (const [id, data] of sessions.entries()) {
        list.push({
            sessionId: id,
            label: data.label,
            status: data.status,
            phone: data.phone,
            name: data.name
        });

        if (data.status === 'CONNECTED') {
            legacyStatus = 'ONLINE';
        } else if (data.status === 'QR_READY' && legacyStatus !== 'ONLINE') {
            legacyStatus = 'WAITING_SCAN';
            legacyQR = data.qr;
        }
    }

    res.json({
        ok: true,
        status: legacyStatus,
        qr: legacyQR,
        uptime: process.uptime(),
        totalSessions: list.length,
        sessions: list
    });
});

// 2. Kirim Pesan Cepat Kompatibel: GET /send?number=...&msg=...
app.get('/send', async (req, res) => {
    const { number, msg, session } = req.query;
    if (!number || !msg) {
        return res.status(400).json({ status: 'error', ok: false, error: 'Parameter number dan msg wajib diisi.' });
    }

    let targetSession = null;
    if (session && sessions.has(session) && sessions.get(session).status === 'CONNECTED') {
        targetSession = sessions.get(session);
    } else if (sessions.has('dinas') && sessions.get('dinas').status === 'CONNECTED') {
        targetSession = sessions.get('dinas');
    } else {
        for (const data of sessions.values()) {
            if (data.status === 'CONNECTED' && data.sock) {
                targetSession = data;
                break;
            }
        }
    }

    if (!targetSession) {
        return res.status(503).json({ status: 'not_ready', ok: false, error: 'Tidak ada sesi WhatsApp yang terhubung di sistem.' });
    }

    try {
        const jid = formatToJid(number);
        await targetSession.sock.sendMessage(jid, { text: msg });
        return res.json({ 
            status: 'success', 
            ok: true, 
            message: 'Pesan terkirim', 
            target: jid, 
            session: targetSession.sessionId 
        });
    } catch (err) {
        return res.status(500).json({ status: 'error', ok: false, message: err.message });
    }
});

// 3. Daftar Sesi
app.get('/sessions', (req, res) => {
    const list = [];
    for (const [id, data] of sessions.entries()) {
        list.push({
            sessionId: id,
            label: data.label,
            status: data.status,
            phone: data.phone,
            name: data.name,
            totalChats: data.chats.size
        });
    }
    res.json({ ok: true, sessions: list });
});

// 4. Buat Sesi Baru (Hasilkan QR)
app.post('/sessions', async (req, res) => {
    const { sessionId, label } = req.body;
    if (!sessionId) {
        return res.status(400).json({ ok: false, error: 'sessionId wajib diisi.' });
    }

    try {
        const sessionData = await createSession(sessionId, label || sessionId);
        res.json({
            ok: true,
            sessionId,
            status: sessionData.status,
            qr: sessionData.qr
        });
    } catch (err) {
        res.status(500).json({ ok: false, error: err.message });
    }
});

// 5. Ambil QR Code
app.get('/sessions/:id/qr', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    res.json({
        ok: true,
        sessionId,
        status: session.status,
        qr: session.qr,
        phone: session.phone
    });
});

// 6. Ambil Status Sesi
app.get('/sessions/:id/status', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    res.json({
        ok: true,
        sessionId,
        status: session.status,
        phone: session.phone,
        name: session.name
    });
});

// 7. Logout Sesi
app.post('/sessions/:id/logout', async (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    try {
        if (session.sock) {
            try {
                await session.sock.logout();
            } catch (e) {}
        }

        session.status = 'DISCONNECTED';
        session.qr = null;
        session.phone = null;
        session.name = null;
        sessions.delete(sessionId);

        const sessionFolder = session.customFolder || path.join(SESSIONS_DIR, sessionId);
        if (fs.existsSync(sessionFolder)) {
            fs.rmSync(sessionFolder, { recursive: true, force: true });
        }

        // Sesi utama langsung disiapkan ulang agar QR baru tampil di Pengaturan.
        if (sessionId === DEFAULT_SESSION_ID) setTimeout(() => ensureDefaultSession(), 3000);

        res.json({ ok: true, message: `Sesi ${sessionId} berhasil di-logout dan dihapus.` });
    } catch (err) {
        res.status(500).json({ ok: false, error: err.message });
    }
});

// 8. Hapus Sesi
app.delete('/sessions/:id', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (session && session.sock) {
        try {
            session.sock.end();
        } catch (e) {}
    }

    sessions.delete(sessionId);
    const sessionFolder = session?.customFolder || path.join(SESSIONS_DIR, sessionId);
    if (fs.existsSync(sessionFolder)) {
        fs.rmSync(sessionFolder, { recursive: true, force: true });
    }

    res.json({ ok: true, message: `Sesi ${sessionId} dihapus.` });
});

// 9. Ambil Riwayat Panggilan / Telepon
app.get('/sessions/:id/calls', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    res.json({ ok: true, calls: session.calls || [] });
});

// 10. Ambil Daftar Percakapan
app.get('/sessions/:id/chats', (req, res) => {
    const sessionId = req.params.id;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    const chatList = Array.from(session.chats.values()).sort((a, b) => {
        return new Date(b.timestamp) - new Date(a.timestamp);
    });

    res.json({ ok: true, chats: chatList });
});

// 11. Ambil Riwayat Pesan
app.get('/sessions/:id/chats/:jid/messages', (req, res) => {
    const { id: sessionId, jid } = req.params;
    const session = sessions.get(sessionId);

    if (!session) {
        return res.status(404).json({ ok: false, error: 'Sesi tidak ditemukan.' });
    }

    const messages = session.messages.get(jid) || [];
    if (session.chats.has(jid)) {
        session.chats.get(jid).unreadCount = 0;
    }

    res.json({ ok: true, messages });
});

// 12. Unduh atau Tampilkan Berkas Media (Gambar, Video, Audio, Dokumen PDF)
app.get('/sessions/:id/media/:msgId', async (req, res) => {
    const { id: sessionId, msgId } = req.params;
    const session = sessions.get(sessionId);
    const sessionFolder = session?.customFolder || path.join(SESSIONS_DIR, sessionId);
    const mediaDir = path.join(sessionFolder, 'media');

    if (!fs.existsSync(mediaDir)) {
        fs.mkdirSync(mediaDir, { recursive: true });
    }

    // 1. Periksa apakah berkas sudah tersimpan di disk
    if (fs.existsSync(mediaDir)) {
        const files = fs.readdirSync(mediaDir);
        const matched = files.find(f => f.startsWith(msgId));
        if (matched) {
            const filePath = path.join(mediaDir, matched);
            return res.sendFile(filePath);
        }
    }

    // 2. Jika belum ada di disk, coba unduh on-demand jika raw message ada di memori
    if (session && session.rawMessages && session.rawMessages.has(msgId)) {
        try {
            const rawMsg = session.rawMessages.get(msgId);
            const buffer = await downloadMessageBuffer(rawMsg, session.sock);
            if (buffer && buffer.length > 0) {
                const info = extractMessageInfo(rawMsg);
                const ext = getExtensionFromMime(info.media?.mimetype || '', info.media?.filename || '');
                const fileName = `${msgId}.${ext}`;
                const savePath = path.join(mediaDir, fileName);
                fs.writeFileSync(savePath, buffer);

                res.setHeader('Content-Type', info.media?.mimetype || 'application/octet-stream');
                res.setHeader('Content-Disposition', `inline; filename="${info.media?.filename || fileName}"`);
                return res.send(buffer);
            }
        } catch (err) {
            console.error(`Gagal mengunduh berkas media on-demand:`, err.message);
        }
    }

    return res.status(404).json({ ok: false, error: 'Berkas media tidak ditemukan atau telah kedaluwarsa.' });
});

// 13. Kirim Pesan Teks
app.post('/sessions/:id/send', async (req, res) => {
    const sessionId = req.params.id;
    const { number, message } = req.body;

    if (!number || !message) {
        return res.status(400).json({ ok: false, error: 'Parameter number dan message wajib diisi.' });
    }

    const session = sessions.get(sessionId);
    if (!session || session.status !== 'CONNECTED' || !session.sock) {
        return res.status(400).json({ ok: false, error: `Sesi ${sessionId} belum terhubung ke WhatsApp.` });
    }

    try {
        const jid = formatToJid(number);
        const result = await session.sock.sendMessage(jid, { text: message });

        const timestamp = new Date().toISOString();
        if (!session.chats.has(jid)) {
            session.chats.set(jid, {
                jid,
                isGroup: jid.endsWith('@g.us'),
                number: jidToNumber(jid),
                name: jidToNumber(jid),
                lastMessage: message,
                timestamp,
                unreadCount: 0,
            });
        } else {
            const chat = session.chats.get(jid);
            chat.lastMessage = message;
            chat.timestamp = timestamp;
        }

        if (!session.messages.has(jid)) {
            session.messages.set(jid, []);
        }
        session.messages.get(jid).push({
            id: result.key.id,
            fromMe: true,
            isGroup: jid.endsWith('@g.us'),
            participant: '',
            participantName: '',
            body: message,
            timestamp,
            status: 'SENT'
        });

        saveSessionStore(sessionId);
        res.json({ ok: true, message: 'Pesan berhasil terkirim.', jid });
    } catch (err) {
        res.status(500).json({ ok: false, error: err.message });
    }
});

// Jalankan Server Gateway
app.listen(PORT, HOST, () => {
    console.log(`====================================================`);
    console.log(` SINDEN Multi-Session WhatsApp Gateway aktif di ${HOST}:${PORT}`);
    console.log(`====================================================`);
    const restoringDefault = restoreSessions();
    // Belum pernah ada sesi utama tersimpan → buat sesi baru agar QR muncul.
    if (!restoringDefault) ensureDefaultSession();
});
