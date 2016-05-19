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
                'info' => $this->processInfo(),
                'raffle' =>$this->processRaffle($html),
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

    public function processInfo()
    {

        return array(
            'title' => (string) S::create('Mega-Sena')->toUpperCase(),
            'createdat'=> Carbon::now('America/Sao_Paulo')->toDateTimeString(),
        );
    }

    public function processRaffle($html)
    {
        $doc = phpQuery::newDocument($html);
        $number_raffle = $doc['div.glb-bloco span.numero-concurso']->text();
        $number = substr($number_raffle, -4);

        $date = $doc['div.glb-bloco span.data-concurso']->text();
        $date_raffle = implode('-',array_reverse(explode('/',substr($date, 0, 10))));



        return array(
            'number'=>$number,
            'date' =>$date_raffle,
            'accumulated' =>$doc['div.glb-bloco span.valor-acumulado']->text(),
            'scores' => array(
                $doc['div.glb-bloco div.resultado-concurso span.numero-sorteado:eq(0)']->text(),
                $doc['div.glb-bloco div.resultado-concurso span.numero-sorteado:eq(1)']->text(),
                $doc['div.glb-bloco div.resultado-concurso span.numero-sorteado:eq(2)']->text(),
                $doc['div.glb-bloco div.resultado-concurso span.numero-sorteado:eq(3)']->text(),
                $doc['div.glb-bloco div.resultado-concurso span.numero-sorteado:eq(4)']->text(),
                $doc['div.glb-bloco div.resultado-concurso span.numero-sorteado:eq(5)']->text()
            ),
            'prize'=>array(
                'winner'=>$doc['div.glb-bloco table.lista-premios tr.premio:eq(0) td.ganhadores-premio']->text(),
                'money' =>$doc['div.glb-bloco table.lista-premios tr.premio:eq(0) td.rateio-premio']->text()
            )

        );
    }
}
