<?php if (!empty($flashError)): ?>
    <div style="margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: rgba(239,68,68,0.12); color: #b91c1c;">
        <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!empty($flashSuccess)): ?>
    <div style="margin-bottom: 14px; padding: 10px 12px; border-radius: 8px; background: rgba(34,197,94,0.12); color: #166534;">
        <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="card" style="padding: 16px; margin-bottom: 16px;">
    <h3 style="margin: 0 0 8px;">Règles automatiques actives</h3>
    <p style="margin: 0 0 12px; color: #6b7280;">
        J-15: génération d'une clé de licence et envoi webhook.<br>
        J-5 à J-0: relances périodiques vers les utilisateurs SaaS.<br>
        J+1+: suspension automatique si non réactivé.
    </p>
    <form method="POST" action="/provider/subscriptions/run">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button class="btn btn-primary" type="submit">Lancer le compteur maintenant (test)</button>
    </form>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #eef2ff;">
                    <th style="padding: 12px; text-align: left;">Entreprise</th>
                    <th style="padding: 12px; text-align: left;">État actuel</th>
                    <th style="padding: 12px; text-align: left; min-width: 520px;">Configuration abonnement</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($companies ?? []) === []): ?>
                    <tr>
                        <td colspan="3" style="padding: 16px; color: #6b7280;">Aucune entreprise trouvée.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach (($companies ?? []) as $company): ?>
                    <?php
                    $companyId = (int) $company['id'];
                    $isLocked = ((int) ($company['app_locked'] ?? 0)) === 1;
                    $subStatus = (string) ($company['subscription_status'] ?? 'active');
                    $endsAt = (string) ($company['subscription_ends_at'] ?? '');
                    $callbackUrl = (string) ($company['provider_callback_url'] ?? '');
                    $reminderInterval = (int) ($company['reminder_interval_days'] ?? 1);
                    $autoEnabled = ((int) ($company['auto_subscription_enabled'] ?? 1)) === 1;
                    ?>
                    <tr style="border-top: 1px solid #e5e7eb;">
                        <td style="padding: 12px; min-width: 220px;">
                            <strong><?= htmlspecialchars((string) ($company['name'] ?? 'Entreprise'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <div style="font-size: 12px; color: #6b7280; margin-top: 3px;">
                                <?= htmlspecialchars((string) ($company['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td style="padding: 12px; min-width: 180px;">
                            <div>
                                <span style="display: inline-block; padding: 4px 8px; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-size: 12px;">
                                    <?= htmlspecialchars($subStatus, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div style="margin-top: 6px; font-size: 12px; color: <?= $isLocked ? '#b91c1c' : '#166534' ?>;">
                                <?= $isLocked ? 'Application verrouillée' : 'Application active' ?>
                            </div>
                            <?php if (!empty($company['last_reminder_at'])): ?>
                                <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                    Dernier rappel: <?= htmlspecialchars((string) $company['last_reminder_at'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;">
                            <form method="POST" action="/provider/companies/<?= $companyId ?>/subscription" style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                                <label style="font-size: 12px; color: #6b7280;">
                                    Date d'échéance
                                    <input type="date" class="form-input" name="subscription_ends_at" value="<?= htmlspecialchars($endsAt, ENT_QUOTES, 'UTF-8') ?>" required>
                                </label>

                                <label style="font-size: 12px; color: #6b7280;">
                                    Intervalle relance (jours)
                                    <input type="number" min="1" max="30" class="form-input" name="reminder_interval_days" value="<?= $reminderInterval ?>">
                                </label>

                                <label style="font-size: 12px; color: #6b7280; grid-column: span 2;">
                                    URL callback fournisseur (POST auto clé J-15)
                                    <input type="url" class="form-input" name="provider_callback_url" value="<?= htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://nestcorporation.com/api/symphony/license-hook">
                                </label>

                                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #111827;">
                                    <input type="checkbox" name="auto_subscription_enabled" <?= $autoEnabled ? 'checked' : '' ?>>
                                    Activer le compteur automatique
                                </label>

                                <div style="display: flex; justify-content: flex-end;">
                                    <button class="btn btn-primary" type="submit">Enregistrer</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
