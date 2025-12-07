import { Controller } from '@hotwired/stimulus';
import 'barcode-detector';

export default class extends Controller {
    static targets = ['video', 'startBtn', 'stopBtn', 'status', 'error', 'method'];
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
        this.barcodeDetector = null;
        this.isProcessing = false;
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

        await this.startNativeScanner();
    }

    stop() {
        this.isScanning = false;
        this.detectionActive = false;
        this.toggleButtons(false);

        // Arrêter le flux vidéo
        if (this.scannerStream) {
            this.scannerStream.getTracks().forEach(track => track.stop());
            this.scannerStream = null;
            this.videoTarget.style.display = 'none';
        }

        this.barcodeDetector = null;
        this.statusTarget.textContent = 'Scan arrêté';
    }

    async startNativeScanner() {
        const isNative = 'BarcodeDetector' in window && window.BarcodeDetector.toString().includes('[native code]');
        this.methodTarget.textContent = isNative
            ? 'Méthode: BarcodeDetector API (native)'
            : 'Méthode: BarcodeDetector API (polyfill)';

        this.barcodeDetector = new BarcodeDetector({ formats: ['ean_13'] });

        try {
            this.scannerStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            this.videoTarget.srcObject = this.scannerStream;
            this.videoTarget.style.display = 'block';

            // Attendre que la vidéo soit prête avant de démarrer la détection
            await new Promise((resolve) => {
                this.videoTarget.addEventListener('loadedmetadata', resolve, { once: true });
            });

            // S'assurer que la vidéo est en train de jouer
            await this.videoTarget.play();

            this.statusTarget.textContent = 'Pointez un code-barres vers la caméra';
            this.startDetectionLoop();

        } catch (err) {
            this.handleError(err);
        }
    }

    startDetectionLoop() {
        const detectLoop = async () => {
            if (!this.detectionActive) return;

            try {
                const barcodes = await this.barcodeDetector.detect(this.videoTarget);
                console.log('DETECTED ', barcodes);
                if (barcodes.length > 0 && this.detectionActive && !this.isProcessing) {
                    await this.handleDetectedBarcode(barcodes[0].rawValue);
                }
            } catch (err) {
                console.error('Detection error:', err);
            }

            if (this.detectionActive) {
                requestAnimationFrame(detectLoop);
            }
        };
        detectLoop();
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

        // ARRÊTER LE SCAN pendant le traitement
        this.isProcessing = true;
        this.pauseScanning();

        this.statusTarget.textContent = `ISBN détecté: ${isbn}`;
        console.log('🔍 Traitement de l\'ISBN:', isbn);

        try {
            // 1. Check DB
            this.statusTarget.textContent = 'Vérification dans la base de données...';
            const checkRes = await this.postJson(this.checkUrlValue, { isbn });

            if (checkRes.exists) {
                alert(`Ce livre existe déjà !\n"${checkRes.book.title}"`);
                return;
            }

            // 2. Search Google
            this.statusTarget.textContent = 'Recherche sur Google Books...';
            const searchRes = await this.postJson(this.searchUrlValue, { isbn });

            if (searchRes.error) {
                alert('Aucun livre trouvé pour cet ISBN.');
                return;
            }

            // 3. Confirm & Save
            const authors = searchRes.authors?.join(', ') || 'Auteur inconnu';
            const confirmMsg = `Titre: ${searchRes.title}\nAuteur(s): ${authors}\n\nVoulez-vous ajouter ce livre à votre bibliothèque ?`;

            if (confirm(confirmMsg)) {
                this.statusTarget.textContent = 'Ajout du livre...';
                await this.postJson(this.saveUrlValue, { isbn });
                alert('Livre ajouté avec succès !');
                this.statusTarget.textContent = `Ajouté: ${searchRes.title}`;
            } else {
                this.statusTarget.textContent = 'Ajout annulé';
            }

        } catch (e) {
            console.error('Erreur:', e);
            this.errorTarget.textContent = e.message || 'Erreur inconnue';
            alert(`Erreur: ${e.message || 'Erreur inconnue'}`);
        } finally {
            // REPRENDRE LE SCAN après le traitement
            this.isProcessing = false;
            this.resumeScanning();
            console.log('✅ Scan repris');
        }
    }

    pauseScanning() {
        console.log('⏸️  Scan en pause');
        this.detectionActive = false;
    }

    resumeScanning() {
        console.log('▶️  Reprise du scan dans 1 seconde...');
        this.statusTarget.textContent = 'Prêt à scanner...';

        setTimeout(() => {
            this.detectionActive = true;

            // Redémarrer la boucle de détection
            if (this.barcodeDetector && this.videoTarget.srcObject) {
                this.startDetectionLoop();
            }
        }, 1000);
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
