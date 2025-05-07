<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class scrappingWeb extends Controller
{
    public $response;
    private $crawler;
    public $url = "https://www.sligro.nl/c.247.html/kruidenierswaren/olien-vetten-boter/olien.html";
    public function iniciar($url, $page)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'User-Agent' => 'Mozilla/5.0',
        ])->get($url . "?page={$page}");
        $this->crawler = new Crawler($response->body());
    }

    public function scrape()
    {
        set_time_limit(0);
        $productos = [];
        $page = 1;
        $count = 1;

        while (true) {
            $this->iniciar($this->url, $page);

            $items = $this->crawler->filter('.cmp-productoverview--grid .cmp-productoverview-product__wrapper');

            if ($items->count() <= 0) {
                break; // No hay más productos, detenemos el scraping
            }

            foreach ($items as $item) {
                $node = new \Symfony\Component\DomCrawler\Crawler($item);

                $marca = $node->filter('.cmp-productoverview-product-info-name__brand')->text();
                $nombre = $node->filter('.cmp-productoverview-product-info-name__name > h5')->text();
                $imagen = $node->filter('.cmp-productoverview-product-info__image')->attr('src');

                // Traducción al español
                $nombre = $this->translateText($nombre);
                $slug = $count . '-' . str_replace(' ', '-', $nombre);  

                $producto = [
                    'id' => $slug,
                    'marca' => $marca,
                    'nombre' => $nombre,
                    'imagen' => "storage/imagenes/{$slug}.jpg",
                ];

                // Descargar imagen
                $imagenData = Http::withoutVerifying()->get($imagen)->body();
                if (!storage_path('aceites/' . $slug)) {
                    Storage::disk('public')->makeDirectory('aceites/' . $slug . '/');
                }
                Storage::disk('public')->put("aceites/{$slug}/{$slug}.jpg", $imagenData);
                Storage::disk('public')->put("aceites/{$slug}/{$slug}.json", json_encode($producto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $productos[] = $producto;
                $count++;
            }
            $page++;
            if ($page > 3) {
                break;
            }
        }

        // Guardar en archivo JSON
        Storage::disk('public')->put('aceites/productos.json', json_encode($productos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'total' => count($productos),
            'productos' => $productos,
            'message' => 'Scraping completado'
        ]);
    }

    public function translateText($translate)
    {
        $apiKey = "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"; // Reemplaza con tu API Key real
        $url = "https://api.groq.com/openai/v1/chat/completions";

        $data = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [
                ["role" => "system", "content" => "SOLO DEBES TRADUCIR, NADA DE INFORMACION SOBRE ALGO O EJEMPLOS ETC. SOLO TRADUCE LO QUE SE TE ENVIE A ESPAÑOL. SI NO LOGRAS TRADUCIRLO POR CUALQUIER RAZON NO ME INTERESA, ME DAS EL MISMO VALOR QUE TE ENVIE Y LISTO, SIN TRADUCIRLO NI DECIRME QUE NO PUDISTE TRADUCIRLO NI NADA, SOLO LO DEVUELVES, TAMPOCO QUIERO QUE ME EXPLIQUES COSAS, SOLO LA TRADUCCION!!!. TODO LO QUE SE TE ENVIA SON NOMBRES DE PRODUCTOS, POR ENDE A VECES NO TIENE UNA TRADUCCION ESPECIFICA, SI TIENE TRADUCCION ME LA DAS, SINO ME DAS EL MISMO VALOR ENVIADO!! PERO NO QUIERO COSAS COMO ESTAS 'No-se-proporcionó-un-texto-para-traducir.-El-valor-que-se-me-envió-fue-'sojaolie'-y-no-tiene-un-equivalente-claro-en-español,-por-lo-que-se-devuelve-el-mismo-valor:-sojaolie_14.' NI SE TE OCURRA ENVIARME ESOS MENSAJES. Dado que estoy creando carpetas y archivos con esas respuestas que me das y me da problemas por la nomeclatura. Debes ser precisa y puntual si te envio 'Nombre del producto' me entregas 'Nombre del producto traducido o no traducido si no se puede'. y asi con todos"],
                ["role" => "user", "content" => $translate]
            ],
            "stream" => false
        ];

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // ⚠️ Deshabilitar verificación SSL (SOLO PARA PRUEBAS)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // No verificar el certificado
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // No verificar el host

        $response = curl_exec($ch);
        $decodedResponse = json_decode($response, true);

        if (isset($decodedResponse['choices'][0]['message']['content'])) {
            return $decodedResponse['choices'][0]['message']['content'];
        } else {
            // Opcional: registrar error para depuración
            Log::error("Respuesta inválida de API Groq:", [
                'response' => $response,
                'parsed' => $decodedResponse,
                'input' => $translate
            ]);
    
            // Devuelve el texto original como fallback
            return $translate;
        }
    }

}
