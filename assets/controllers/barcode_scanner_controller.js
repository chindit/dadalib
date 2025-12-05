import { Controller } from '@hotwired/stimulus';
import { Html5Qrcode } from 'html5-qrcode';

export default class extends Controller {
    static targets = ['video', 'reader', 'startBtn', 'stopBtn', 'status', 'error', 'method'];
    static values = {
        checkUrl: String,
        searchUrl: String,
        saveUrl: String
    }

    connect() {
        this.isScanning = false;
        this.detectionActive = false;
        this.lastScannedISBN = null;
        this.lastScanTime = 0;
        this.scannerStream = null;
        this.html5QrCode = null;
    }

    disconnect() {
        this.stop();
    }

    async start() {
        if (this.isScanning) return;

        this.isScanning = true;
        this.detectionActive = true;
        this.toggleButtons(true);
        this.statusTarget.textContent = 'Initialisation...';
        this.errorTarget.textContent = '';

        if ('BarcodeDetector' in window) {
            await this.startNativeScanner();
        } else {
            await this.startLibraryScanner();
        }
    }

    stop() {
        this.isScanning = false;
        this.detectionActive = false;
        this.toggleButtons(false);

        // Stop Native
        if (this.scannerStream) {
            this.scannerStream.getTracks().forEach(track => track.stop());
            this.scannerStream = null;
            this.videoTarget.style.display = 'none';
        }

        // Stop Library
        if (this.html5QrCode) {
            this.html5QrCode.stop().catch(err => console.error(err));
            this.readerTarget.style.display = 'none';
            this.html5QrCode = null;
        }

        this.statusTarget.textContent = 'Scan arrêté';
    }

    async startNativeScanner() {
        this.methodTarget.textContent = 'Méthode: BarcodeDetector API (native)';
        const barcodeDetector = new BarcodeDetector({ formats: ['ean_13'] });

        try {
            this.scannerStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            this.videoTarget.srcObject = this.scannerStream;
            this.videoTarget.style.display = 'block';
            this.statusTarget.textContent = 'Pointez un code-barres vers la caméra';

            const detectLoop = async () => {
                if (!this.detectionActive) return;
                try {
                    const barcodes = await barcodeDetector.detect(this.videoTarget);
                    if (barcodes.length > 0 && this.detectionActive) {
                        this.handleDetectedBarcode(barcodes[0].rawValue);
                    }
                } catch (err) {
                    console.error(err);
                }
                if (this.detectionActive) requestAnimationFrame(detectLoop);
            };
            detectLoop();

        } catch (err) {
            this.handleError(err);
        }
    }

    async startLibraryScanner() {
        this.methodTarget.textContent = 'Méthode: html5-qrcode (fallback)';
        this.videoTarget.style.display = 'none';
        this.readerTarget.style.display = 'block';

        this.html5QrCode = new Html5Qrcode(this.readerTarget.id);

        try {
            await this.html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    if (this.detectionActive) this.handleDetectedBarcode(decodedText);
                },
                () => {} // Ignore errors
            );
            this.statusTarget.textContent = 'Pointez un code-barres vers la caméra';
        } catch (err) {
            this.handleError(err);
        }
    }

    async handleDetectedBarcode(rawIsbn) {
        let isbn = rawIsbn.replace(/[-\s]/g, '').replace(/^0+/, '');

        // Validation basique
        if ((!isbn.startsWith('978') && !isbn.startsWith('979')) || isbn.length !== 13) return;

        // Anti-rebond
        const now = Date.now();
        if (isbn === this.lastScannedISBN && (now - this.lastScanTime) < 5000) return;

        this.lastScannedISBN = isbn;
        this.lastScanTime = now;
        this.detectionActive = false; // Pause

        this.statusTarget.textContent = `ISBN détecté: ${isbn}`;

        try {
            // 1. Check DB
            const checkRes = await this.postJson(this.checkUrlValue, { isbn });
            if (checkRes.exists) {
                alert('Ce livre existe déjà !');
                this.resetDetection();
                return;
            }

            // 2. Search Google
            this.statusTarget.textContent = 'Recherche Google Books...';
            const searchRes = await this.postJson(this.searchUrlValue, { isbn });

            if (searchRes.error) {
                alert('Livre introuvable.');
                this.resetDetection();
                return;
            }

            // 3. Confirm & Save
            const confirmMsg = `Ajouter "${searchRes.title}" ?`;
            if (confirm(confirmMsg)) {
                await this.postJson(this.saveUrlValue, { isbn });
                alert('Livre ajouté !');
            }

        } catch (e) {
            console.error(e);
            this.errorTarget.textContent = e.message || 'Erreur inconnue';
        } finally {
            this.resetDetection();
        }
    }

    resetDetection() {
        this.statusTarget.textContent = 'Prêt à scanner...';
        setTimeout(() => { this.detectionActive = true; }, 1000);
    }

    async postJson(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const json = await response.json();
        if (!response.ok && !json.error) throw new Error(response.statusText);
        return json;
    }

    handleError(err) {
        this.errorTarget.textContent = `Erreur caméra: ${err.message || err}`;
        this.stop();
    }

    toggleButtons(isScanning) {
        this.startBtnTarget.classList.toggle('hidden', isScanning);
        this.stopBtnTarget.classList.toggle('hidden', !isScanning);
    }
}
