import * as Ably from 'ably';
import { ChatClient } from '@ably/chat';

const ablyKey = import.meta.env.VITE_ABLY_KEY;

if (ablyKey && typeof ablyKey === 'string' && ablyKey.trim() !== '') {
    try {
        const userId = document.querySelector('meta[name="user-id"]')?.content || 'guest';
        const realtime = new Ably.Realtime({ 
            key: ablyKey,
            clientId: userId 
        });
        window.chatClient = new ChatClient(realtime);
    } catch (e) {
        console.warn("Radar SI SINDEN (Ably Chat) tidak aktif:", e?.message || e);
    }
}

console.log("Radar SI SINDEN: Mesin Siap");