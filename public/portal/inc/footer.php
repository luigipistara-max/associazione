    </div> <!-- Close container-fluid -->
    
    <!-- PWA Install Banner -->
    <div id="pwaInstallBanner" class="pwa-install-banner" style="display: none;">
        <div class="pwa-banner-content">
            <div class="pwa-banner-icon">📱</div>
            <div class="pwa-banner-text">
                <strong>Installa l'App</strong>
                <p>Aggiungi il Portale Soci alla schermata home</p>
            </div>
            <button id="pwaInstallBtn" class="btn btn-light btn-sm">Installa</button>
            <button id="pwaCloseBanner" class="btn-close btn-close-white" aria-label="Chiudi"></button>
        </div>
    </div>
    
    <footer class="mt-5 py-3 bg-light text-center text-muted">
        <div class="container">
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo h($siteName); ?>
                <?php if (!empty($assocInfo['website'])): ?>
                    | <a href="<?php echo h($assocInfo['website']); ?>" target="_blank" class="text-decoration-none"><?php echo h($assocInfo['website']); ?></a>
                <?php endif; ?>
            </p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- PWA Install Script -->
    <script>
        let deferredPrompt;
        const installBanner = document.getElementById('pwaInstallBanner');
        const installBtn = document.getElementById('pwaInstallBtn');
        const closeBanner = document.getElementById('pwaCloseBanner');
        
        // Check if already installed or dismissed
        const bannerDismissed = localStorage.getItem('pwaBannerDismissed');
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67 and earlier from automatically showing the prompt
            e.preventDefault();
            // Stash the event so it can be triggered later
            deferredPrompt = e;
            
            // Show install banner if not dismissed and not already installed
            if (!bannerDismissed && !isStandalone) {
                setTimeout(() => {
                    installBanner.style.display = 'block';
                }, 3000); // Show after 3 seconds
            }
        });
        
        installBtn.addEventListener('click', async () => {
            if (!deferredPrompt) {
                return;
            }
            
            // Show the install prompt
            deferredPrompt.prompt();
            
            // Wait for the user to respond to the prompt
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User response to the install prompt: ${outcome}`);
            
            // Clear the deferred prompt
            deferredPrompt = null;
            
            // Hide the banner
            installBanner.style.display = 'none';
        });
        
        closeBanner.addEventListener('click', () => {
            installBanner.style.display = 'none';
            localStorage.setItem('pwaBannerDismissed', 'true');
        });
        
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            installBanner.style.display = 'none';
        });
    </script>
    
    <?php if (isset($extraJs)): ?>
        <?php echo $extraJs; ?>
    <?php endif; ?>
</body>
</html>
