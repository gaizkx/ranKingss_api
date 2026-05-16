<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()

    // ── Rutas ──────────────────────────────────────────────────────────────
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])

    // ── PHP: moderniza según la versión declarada en composer.json ─────────
    ->withPhpSets()

    // ── Sets preparados ────────────────────────────────────────────────────
    ->withPreparedSets(
        codingStyle: true,            // estilo consistente
        typeDeclarationDocblocks: true, // añade @param, @return, @var en docblocks
                                        // + @extends, @template, @implements inferibles
        privatization: true,          // hace private lo que puede serlo
        naming: true,                 // mejora nombres de variables y métodos
        instanceOf: true,             // sustituye is_a() por instanceof
        earlyReturn: true,            // convierte ifs anidados en early returns
        symfonyCodeQuality: true,     // reglas específicas de Symfony:
                                        // inyección por constructor, controllers, etc.
    )

    // ── Symfony & paquetes: aplica sets de upgrade según composer.json ─────
    ->withComposerBased(
        symfony: true,   // symfony/framework-bundle, console, security…
        doctrine: true,  // doctrine/orm, migrations…
        twig: true,       // twig/twig
        phpunit: true,   // phpunit/phpunit
    )

    // ── Integración con el contenedor de Symfony ───────────────────────────
    // Necesario para reglas que resuelven servicios por tipo (ej. FormType)
    // Genera el XML con: bin/console cache:clear --env=dev
    ->withSymfonyContainerXml(
        __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml'
    )

    // ── Niveles graduales ─────────────────────────────────────────────────
    ->withTypeCoverageLevel(10)   // cobertura de tipos: propiedades, retornos, params
    ->withDeadCodeLevel(10)       // eliminación de código muerto
    ->withCodeQualityLevel(10)    // calidad general del código

    // ── Imports ───────────────────────────────────────────────────────────
    // Añade `use` statements automáticamente y elimina los no usados
    ->withImportNames(
        importNames: true,
        importShortClasses: false,
        removeUnusedImports: true,
    )

    // ── Exclusiones ───────────────────────────────────────────────────────
    ->withSkip([
        __DIR__ . '/config',
        __DIR__ . '/public/index.php',
        // Excluye carpetas o reglas concretas si alguna da falsos positivos:
        // __DIR__ . '/src/Legacy',
        // Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector::class,
    ])

    // ── Rendimiento ───────────────────────────────────────────────────────
    ->withParallel()                              // usa todos los cores disponibles
    ->withCache(cacheDirectory: '/tmp/rector');   // omite archivos no modificados
