<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class scrappingWeb extends Controller
{
    public $response;
    private $crawler;

    public function __construct($url = "https://www.sligro.nl/c.247.html/kruidenierswaren/olien-vetten-boter/olien.html")
    {
        $response = Http::withoutVerifying()->withHeaders([
            'User-Agent' => 'Mozilla/5.0',
        ])->get($url);
        $this->crawler = new Crawler($response->body());
    }

    public function scrape()
    {
        $productos = [];
        $page = 1;
        $bool = true;
        while($bool) {
            $items = $this->crawler->filter('.cmp-productoverview--grid .cmp-productoverview-product__wrapper'); // Ajusta selector
            if ($items->count() === 0) break;

            $items->each(function ($node) use (&$productos, &$bool) {
                $marca = $node->filter('.cmp-productoverview-product-info-name__brand')->text();
                $nombre = $node->filter('.cmp-productoverview-product-info-name__name > h5')->text();
                $imagen = $node->filter('.cmp-productoverview-product-info__image')->attr('src');
                $slug = Str::slug($nombre);

                // Traducción al español
                $nombre = $this->translateText($nombre);
                $slug = $this->translateText($slug);
                
                // Descargar imagen
                $imagenData = Http::withoutVerifying()->get($imagen)->body();
                Storage::disk('public')->put("imagenes/{$slug}.png", $imagenData);

                $productos[] = [
                    'id' => $slug,
                    'marca' => $marca,
                    'nombre' => $nombre,
                    'imagen' => "storage/imagenes/{$slug}.jpg",
                    'src' => $imagen,
                ];
                
                if(count($productos) >= 5) {
                    // Guardar en archivo JSON
                    Storage::disk('public')->put('data/productos.json', json_encode($productos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                    $bool = false;
                    // Salir del bucle
                    return response()->json(['total' => count($productos), 'message' => 'Scraping completado']); // Salir si se ha alcanzado el límite
                }
                sleep(1); // Esperar 1 segundo entre solicitudes
            });

            $page++;

            $response = Http::withoutVerifying()->withHeaders([
                'User-Agent' => 'Mozilla/5.0',
            ])->get("https://www.sligro.nl/c.248.html/kruidenierswaren/olien-vetten-boter/olien/olijfolie.html?query=%3A%3Acategory%3A248&page={$page}");

            $this->crawler = new Crawler($response->body());

        }

        // Guardar en archivo JSON
        Storage::disk('public')->put('data/productos.json', json_encode($productos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json(['total' => count($productos), 'message' => 'Scraping completado']);

    }

    public function translateText($translate)
    {
        $apiKey = "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"; // Reemplaza con tu API Key real
        $url = "https://api.groq.com/openai/v1/chat/completions";

        $data = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [
                ["role" => "system", "content" => "Hola eres un experto en neerlandes y en español, traduce el siguiente mensaje sin mensajes adicionales."],
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

        if (curl_errno($ch)) {
            echo "Error en la solicitud cURL: " . curl_error($ch);
        } else {
            $decodedResponse = json_decode($response, true);
            return $decodedResponse['choices'][0]['message']['content']; // Mostrar la respuesta de la API
        }

        curl_close($ch);
    }

}
