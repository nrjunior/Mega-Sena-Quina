<?php
// Routes

$app->get('/', App\Action\HomeAction::class)->setName('homepage');

$app->get('/resultados/sena', App\Action\Resultados\SenaAction::class)->setName('resultados.sena');

$app->get('/resultados/quina', App\Action\Resultados\QuinaAction::class)->setName('resultados.quina');