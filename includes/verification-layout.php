<?php

function verification_files_panel(array $record, bool $vehicle, ?string $manageUrl = null): void
{
    foreach (VERIFICATION_FILES as $kind => $spec):
        if ($spec['vehicle'] !== $vehicle) {
            continue;
        }
        $available = verification_file_exists($kind, $record[$spec['column']]);
        $url = verification_file_url($kind, (int) $record['id']);
        ?>
        <div class="verification-file">
            <div class="verification-row">
                <strong><?= e($spec['label']) ?></strong>
                <span class="badge <?= $available ? '' : 'status-expired' ?>"><?= $available ? 'Terunggah' : 'Belum diunggah' ?></span>
                <?php if ($available): ?>
                    <a href="<?= e($url) ?>"><?= $spec['private'] ? 'Unduh' : 'Lihat' ?> <?= e($spec['label']) ?></a>
                <?php endif; ?>
            </div>
            <?php if ($available && !$spec['private']): ?>
                <img class="verification-preview" src="<?= e($url) ?>" alt="<?= e($spec['label']) ?>">
            <?php endif; ?>
            <?php if ($manageUrl !== null): ?>
                <form method="post" action="<?= e($manageUrl) ?>" enctype="multipart/form-data" class="form-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="kind" value="<?= e($kind) ?>">
                    <label><?= $available ? 'Ganti' : 'Pilih' ?> <?= e($spec['label']) ?>
                        <input type="file" name="upload" accept="<?= $spec['private'] ? 'application/pdf,' : '' ?>image/jpeg,image/png" required>
                        <small><?= $spec['private'] ? 'PDF, JPEG, atau PNG · maksimal 5 MB' : 'JPEG atau PNG · maksimal 2 MB' ?></small>
                    </label>
                    <div><button class="button button-small" name="action" value="upload">Unggah</button></div>
                </form>
                <?php if ($available): ?>
                    <form method="post" action="<?= e($manageUrl) ?>" class="form-stack" data-confirm="Hapus berkas ini?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kind" value="<?= e($kind) ?>">
                        <div><button class="text-danger" name="action" value="remove">Hapus <?= e($spec['label']) ?></button></div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach;
}
