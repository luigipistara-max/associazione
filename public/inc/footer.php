                </div>
            </main>
        </div>
    </div>

    <footer>
        <?php
        $config = require __DIR__ . '/../../src/config.php';
        $siteName = $config['app']['name'] ?? 'Associazione';
        ?>
        © <?php echo date('Y'); ?> <?php echo h($siteName); ?> - Powered with <strong>AssoLife</strong> by Luigi Pistarà
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
