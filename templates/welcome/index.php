<?php

use App\Support\View;

/** @var string $appName */
/** @var string $appVersion */
/** @var array<string, mixed> $t */
/** @var string $locale */

$ctaHref = '/login';
?>
<header class="welcome-hero">
    <div class="welcome-hero-visual" aria-hidden="true">
        <svg class="welcome-pipeline" viewBox="0 0 960 720" preserveAspectRatio="xMidYMid slice">
            <defs>
                <linearGradient id="pipeGlow" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#3d8bfd" stop-opacity=".9"/>
                    <stop offset="55%" stop-color="#7eb0ff" stop-opacity=".55"/>
                    <stop offset="100%" stop-color="#c9a227" stop-opacity=".45"/>
                </linearGradient>
                <pattern id="grid" width="48" height="48" patternUnits="userSpaceOnUse">
                    <path d="M48 0H0V48" fill="none" stroke="rgba(255,255,255,.04)" stroke-width="1"/>
                </pattern>
            </defs>
            <rect width="960" height="720" fill="url(#grid)"/>
            <path class="welcome-pipeline-flow" d="M40 156 H160 L224 220 H340 L400 280 H520 L580 340 H920" fill="none" stroke="url(#pipeGlow)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <path class="welcome-pipeline-flow welcome-pipeline-flow-b" d="M120 540 H280 L360 460 H520 L600 400 H800" fill="none" stroke="url(#pipeGlow)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/>
            <g class="welcome-nodes" fill="none" stroke="#8eb6ff" stroke-width="1.5">
                <rect x="64" y="132" width="48" height="48" rx="10"/>
                <rect x="276" y="196" width="48" height="48" rx="10"/>
                <rect x="436" y="256" width="48" height="48" rx="10"/>
                <rect x="656" y="316" width="48" height="48" rx="10"/>
                <rect x="856" y="316" width="48" height="48" rx="10"/>
            </g>
            <g class="welcome-pulse" fill="#3d8bfd">
                <circle cx="88" cy="156" r="4"/>
                <circle cx="300" cy="220" r="4"/>
                <circle cx="460" cy="280" r="4"/>
                <circle cx="680" cy="340" r="4"/>
                <circle cx="880" cy="340" r="4"/>
            </g>
        </svg>
        <div class="welcome-hero-haze"></div>
    </div>

    <div class="welcome-hero-inner">
        <p class="welcome-brand">
            <img class="welcome-logo" src="/assets/brand/logo-on-dark.png" width="748" height="156" alt="<?= View::e($appName) ?>">
        </p>
        <p class="welcome-kicker"><?= View::e((string) ($t['version_kicker'] ?? ('Version ' . $appVersion))) ?></p>
        <h1 class="welcome-headline"><?= View::e((string) $t['headline']) ?></h1>
        <p class="welcome-lead"><?= View::e((string) $t['lead']) ?></p>
        <div class="welcome-cta">
            <a class="welcome-btn welcome-btn-primary" href="<?= View::e($ctaHref) ?>"><?= View::e((string) $t['cta_login']) ?></a>
            <a class="welcome-btn welcome-btn-ghost" href="#demo"><?= View::e((string) ($t['cta_demo'] ?? 'Demo')) ?></a>
            <a class="welcome-btn welcome-btn-ghost" href="#modules"><?= View::e((string) $t['cta_modules']) ?></a>
            <a class="welcome-btn welcome-btn-ghost" href="<?= View::e((string) ($t['github_href'] ?? '')) ?>" target="_blank" rel="noopener noreferrer"><?= View::e((string) ($t['cta_github'] ?? 'GitHub')) ?></a>
        </div>
    </div>
</header>

<main>
    <section class="welcome-section welcome-section-alt welcome-reveal" id="demo">
        <div class="welcome-section-inner welcome-section-inner-wide welcome-section-inner-demo">
            <h2><?= View::e((string) ($t['demo_title'] ?? '')) ?></h2>
            <p class="welcome-section-lead"><?= View::e((string) ($t['demo_lead'] ?? '')) ?></p>
            <div class="welcome-demo">
                <dl class="welcome-demo-grid">
                    <div>
                        <dt><?= View::e((string) ($t['demo_url_label'] ?? 'URL')) ?></dt>
                        <dd><a href="<?= View::e((string) ($t['demo_url'] ?? '')) ?>"><?= View::e((string) ($t['demo_url'] ?? '')) ?></a></dd>
                    </div>
                    <div>
                        <dt><?= View::e((string) ($t['demo_email_label'] ?? '')) ?></dt>
                        <dd><code><?= View::e((string) ($t['demo_email'] ?? '')) ?></code></dd>
                    </div>
                    <div>
                        <dt><?= View::e((string) ($t['demo_password_label'] ?? '')) ?></dt>
                        <dd><code><?= View::e((string) ($t['demo_password'] ?? '')) ?></code></dd>
                    </div>
                </dl>
                <p class="welcome-demo-note"><?= View::e((string) ($t['demo_note'] ?? '')) ?></p>
                <div class="welcome-cta">
                    <a class="welcome-btn welcome-btn-primary" href="<?= View::e($ctaHref) ?>"><?= View::e((string) ($t['cta_demo'] ?? $t['cta_login'])) ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="welcome-section welcome-reveal" id="purpose">
        <div class="welcome-section-inner">
            <h2><?= View::e((string) $t['purpose_title']) ?></h2>
            <p><?= View::e((string) $t['purpose_body']) ?></p>
        </div>
    </section>

    <section class="welcome-section welcome-section-alt welcome-reveal" id="modules">
        <div class="welcome-section-inner welcome-section-inner-wide welcome-section-inner-modules">
            <h2><?= View::e((string) $t['modules_title']) ?></h2>
            <p class="welcome-section-lead"><?= View::e((string) $t['modules_lead']) ?></p>
            <ul class="welcome-modules">
                <?php foreach ($t['modules'] as $module): ?>
                    <?php if (!is_array($module)) {
                        continue;
                    } ?>
                    <li class="welcome-module">
                        <h3><?= View::e((string) ($module['name'] ?? '')) ?></h3>
                        <p><?= View::e((string) ($module['summary'] ?? '')) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="welcome-section welcome-reveal" id="cycle">
        <div class="welcome-section-inner welcome-section-inner-wide">
            <h2><?= View::e((string) $t['cycle_title']) ?></h2>
            <p class="welcome-section-lead"><?= View::e((string) $t['cycle_lead']) ?></p>
            <?php foreach (($t['cycles'] ?? []) as $cycle): ?>
                <?php if (!is_array($cycle)) {
                    continue;
                } ?>
                <article class="welcome-cycle-block">
                    <h3><?= View::e((string) ($cycle['title'] ?? '')) ?></h3>
                    <p class="welcome-cycle-lead"><?= View::e((string) ($cycle['lead'] ?? '')) ?></p>
                    <figure class="welcome-cycle-diagram">
                        <img
                            src="<?= View::e((string) ($cycle['image'] ?? '')) ?>"
                            width="1536"
                            height="864"
                            alt="<?= View::e((string) ($cycle['image_alt'] ?? '')) ?>"
                            loading="lazy"
                            decoding="async"
                        >
                    </figure>
                    <ol class="welcome-steps">
                        <?php foreach (($cycle['steps'] ?? []) as $step): ?>
                            <?php if (!is_array($step)) {
                                continue;
                            } ?>
                            <li>
                                <h4><?= View::e((string) ($step['title'] ?? '')) ?></h4>
                                <p><?= View::e((string) ($step['body'] ?? '')) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="welcome-section welcome-section-alt welcome-reveal" id="self-host">
        <div class="welcome-section-inner">
            <h2><?= View::e((string) ($t['install_title'] ?? '')) ?></h2>
            <p><?= View::e((string) ($t['install_body'] ?? '')) ?></p>
            <div class="welcome-cta">
                <a class="welcome-btn welcome-btn-primary" href="<?= View::e((string) ($t['github_href'] ?? '')) ?>" target="_blank" rel="noopener noreferrer"><?= View::e((string) ($t['cta_github'] ?? 'GitHub')) ?></a>
            </div>
        </div>
    </section>

    <section class="welcome-section welcome-section-close welcome-reveal">
        <div class="welcome-section-inner">
            <h2><?= View::e((string) $t['close_title']) ?></h2>
            <p><?= View::e((string) $t['close_body']) ?></p>
            <div class="welcome-cta">
                <a class="welcome-btn welcome-btn-primary" href="<?= View::e($ctaHref) ?>"><?= View::e((string) $t['cta_login']) ?></a>
                <a class="welcome-btn welcome-btn-ghost" href="<?= View::e((string) ($t['github_href'] ?? '')) ?>" target="_blank" rel="noopener noreferrer"><?= View::e((string) ($t['cta_github'] ?? 'GitHub')) ?></a>
            </div>
        </div>
    </section>
</main>

<footer class="welcome-footer">
    <img class="welcome-footer-mark" src="/assets/brand/icon.png" width="22" height="22" alt="">
    <span><?= View::e($appName) ?></span>
    <span class="welcome-footer-sep" aria-hidden="true">·</span>
    <span>v<?= View::e($appVersion) ?></span>
    <span class="welcome-footer-sep" aria-hidden="true">·</span>
    <a href="<?= View::e((string) ($t['github_href'] ?? '')) ?>" target="_blank" rel="noopener noreferrer">GitHub</a>
</footer>
