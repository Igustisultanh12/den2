/**
 * Utilitas Suara Notifikasi & Panggilan Dinas SINDEN Denintel Koarmada 2
 * Memanfaatkan Web Audio API murni tanpa ketergantungan berkas audio eksternal.
 * Menghasilkan nada lonceng pesan, nada dering panggilan masuk, nada tunggu panggil keluar, dan nada diskoneksi.
 */

let audioCtx = null;
let incomingRingTimer = null;
let outgoingDialTimer = null;

const getAudioContext = () => {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) return null;
  if (!audioCtx) {
    audioCtx = new AudioContextClass();
  }
  if (audioCtx.state === 'suspended') {
    audioCtx.resume();
  }
  return audioCtx;
};

/**
 * Memainkan nada lonceng ganda saat pesan obrolan baru masuk
 */
export const playNotificationSound = () => {
  try {
    const ctx = getAudioContext();
    if (!ctx) return;

    const now = ctx.currentTime;

    // Nada pertama (880 Hz - A5)
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();

    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(880, now);

    gain1.gain.setValueAtTime(0.12, now);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.25);

    osc1.connect(gain1);
    gain1.connect(ctx.destination);

    osc1.start(now);
    osc1.stop(now + 0.25);

    // Nada kedua (1320 Hz - E6) berjarak 0.08 detik
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();

    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(1320, now + 0.08);

    gain2.gain.setValueAtTime(0.15, now + 0.08);
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.45);

    osc2.connect(gain2);
    gain2.connect(ctx.destination);

    osc2.start(now + 0.08);
    osc2.stop(now + 0.45);
  } catch (err) {
    console.debug('Pemutaran nada notifikasi diabaikan:', err);
  }
};

/**
 * Memainkan nada dering berulang untuk panggilan video dinas masuk
 */
export const startIncomingCallRing = () => {
  stopIncomingCallRing();

  const playRingCycle = () => {
    try {
      const ctx = getAudioContext();
      if (!ctx) return;

      const now = ctx.currentTime;

      // Nada ganda harmonis panggilan masuk (587 Hz dan 880 Hz)
      const playTone = (freq1, freq2, startTime, duration) => {
        const oscA = ctx.createOscillator();
        const oscB = ctx.createOscillator();
        const gain = ctx.createGain();

        oscA.type = 'sine';
        oscB.type = 'sine';
        oscA.frequency.setValueAtTime(freq1, startTime);
        oscB.frequency.setValueAtTime(freq2, startTime);

        gain.gain.setValueAtTime(0.15, startTime);
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

        oscA.connect(gain);
        oscB.connect(gain);
        gain.connect(ctx.destination);

        oscA.start(startTime);
        oscB.start(startTime);
        oscA.stop(startTime + duration);
        oscB.stop(startTime + duration);
      };

      // Pulsa 1
      playTone(587, 880, now, 0.45);
      // Pulsa 2
      playTone(659, 988, now + 0.55, 0.55);
    } catch (err) {
      console.debug('Dering panggilan masuk diabaikan:', err);
    }
  };

  playRingCycle();
  incomingRingTimer = setInterval(playRingCycle, 2600);
};

export const stopIncomingCallRing = () => {
  if (incomingRingTimer) {
    clearInterval(incomingRingTimer);
    incomingRingTimer = null;
  }
};

/**
 * Memainkan nada tunggu panggil keluar berirama
 */
export const startOutgoingDialRing = () => {
  stopOutgoingDialRing();

  const playDialCycle = () => {
    try {
      const ctx = getAudioContext();
      if (!ctx) return;

      const now = ctx.currentTime;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(440, now);

      gain.gain.setValueAtTime(0.08, now);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 1.2);

      osc.connect(gain);
      gain.connect(ctx.destination);

      osc.start(now);
      osc.stop(now + 1.2);
    } catch (err) {
      console.debug('Nada panggil keluar diabaikan:', err);
    }
  };

  playDialCycle();
  outgoingDialTimer = setInterval(playDialCycle, 3200);
};

export const stopOutgoingDialRing = () => {
  if (outgoingDialTimer) {
    clearInterval(outgoingDialTimer);
    outgoingDialTimer = null;
  }
};

/**
 * Memainkan nada penutupan atau diskoneksi panggilan
 */
export const playCallEndedSound = () => {
  try {
    const ctx = getAudioContext();
    if (!ctx) return;

    const now = ctx.currentTime;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.type = 'triangle';
    osc.frequency.setValueAtTime(480, now);
    osc.frequency.exponentialRampToValueAtTime(240, now + 0.35);

    gain.gain.setValueAtTime(0.12, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.start(now);
    osc.stop(now + 0.35);
  } catch (err) {
    console.debug('Nada akhir panggilan diabaikan:', err);
  }
};
