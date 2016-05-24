<?php
namespace App\Action\Resultados;

use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use phpQuery;
use Carbon\Carbon;
use Thapp\XmlBuilder\XMLBuilder;
use Thapp\XmlBuilder\Normalizer;
use FileSystemCache;
use Stringy\Stringy as S;

final class SenaAction
{
    private $view;
    private $logger;

    public function __construct(Twig $view, LoggerInterface $logger)
    {
        $this->view = $view;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response, $args)
    {
        FileSystemCache::$cacheDir = __DIR__ . '/../../../../cache/tmp';
        $key = FileSystemCache::generateCacheKey('cache-feed_SenaAction', null);
        $data = FileSystemCache::retrieve($key);

        if($data === false)
        {
            $doc = phpQuery::newDocumentFileHTML('http://g1.globo.com/loterias/megasena.html');
            $doc->find('head')->remove();
            $doc->find('meta')->remove();
            $doc->find('noscript')->remove();
            $doc->find('script')->remove();
            $doc->find('style')->remove();
            $doc->find('path')->remove();
            $doc->find('svg')->remove();
            $doc->find('footer')->remove();

            $html = pq('body');

            $data = array(
                'info' => $this->processInfo($html),
                'awards' => $this->processAwards($html),
                'numbers' => $this->processNumber($html),
            );

            FileSystemCache::store($key, $data, 1800);
        }

        $xmlBuilder = new XMLBuilder('root');
        $xmlBuilder->setSingularizer(function ($name) {
            if ('scores' === $name) {
                return 'score';
            }

            return $name;
        });

        $xmlBuilder->load($data);
        $xml_output = $xmlBuilder->createXML(true);
        $response->write($xml_output);
        $response = $response->withHeader('content-type', 'text/xml');
        return $response;
    }

    public function processInfo($html)
    {
        $doc = phpQuery::newDocument($html);
        $date = $doc['div.glb-bloco span.data-concurso']->text();
        $date = implode('-',array_reverse(explode('/',substr($date, 0, 10))));

        $number = $doc['div.glb-bloco span.numero-concurso']->text();
        $number = substr($number, -4);

        return array(
            'title' => (string) S::create('Mega Sena')->toUpperCase(),
            'date'=> $date,
            'contest'=> $number,
        );
    }

    public function processAwards($html)
    {
        $doc = phpQuery::newDocument($html);

        if($doc['div.glb-bloco table.lista-premios tr.premio:eq(0) td.ganhadores-premio']->text() == 'Acumulou !!!')
        {
            $line1 = 'Próximo prêmio é de ' . $doc['div.glb-bloco span.valor-acumulado']->text();
            $line2 = 'Acumulou!!!';
        }
        else
        {
            $line1 = $doc['div.glb-bloco table.lista-premios tr.premio:eq(0) td.ganhadores-premio']->text();
            $line2 = trim($doc['div.glb-bloco table.lista-premios tr.premio:eq(0) td.rateio-premio']->text());
        }

        return array(
            'winners' => array(
                'line1' => $line1,
                'line2' => $line2
            )
        );
    }

    public function processNumber($html)
    {
        $doc = phpQuery::newDocument($html);

        $data = array();

        foreach($doc['div.glb-bloco div.resultado-concurso span.numero-sorteado'] as $key => $span)
        {
            $pq = pq($span);
            $data[$key]['number'] = str_pad($pq->text(), 2, '0', STR_PAD_LEFT) ;
        }

        return array(
            'scores' => $data
        );
    }
}
