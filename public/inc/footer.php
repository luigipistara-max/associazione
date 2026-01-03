                </div>
            </main>
        </div>
    </div>

    <footer>
        <?php
        $config = require __DIR__ . '/../../src/config.php';
        $assocInfo = getAssociationInfo();
        $siteName = $assocInfo['name'] ?? $config['app']['name'] ?? 'Associazione';
        ?>
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-start">
                    <strong><?php echo h($siteName); ?></strong><br>
                    <?php if (!empty($assocInfo['address'])): ?>
                        <small><?php echo nl2br(h($assocInfo['address'])); ?></small><br>
                    <?php endif; ?>
                    <?php if (!empty($assocInfo['email'])): ?>
                        <small>Email: <?php echo h($assocInfo['email']); ?></small><br>
                    <?php endif; ?>
                    <?php if (!empty($assocInfo['phone'])): ?>
                        <small>Tel: <?php echo h($assocInfo['phone']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-end">
                    © <?php echo date('Y'); ?> <?php echo h($siteName); ?><br>
                    <small>Powered with <strong>AssoLife</strong> by Luigi Pistarà</small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
