    </div> <!-- Close container-fluid -->
    
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
    <?php if (isset($extraJs)): ?>
        <?php echo $extraJs; ?>
    <?php endif; ?>
</body>
</html>
