<?php

use App\Http\Controllers\scrappingWeb;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::controller(scrappingWeb::class)->group(function () {
    Route::get('/scrape', 'scrape');
});

Route::get('/apiTest', function () {
    $apiKey = "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"; // Reemplaza con tu API Key real
    $url = "https://api.groq.com/openai/v1/chat/completions";

    $text = "Nee, alleen ondernemingen en organisaties die ingeschreven zijn bij de Kamer van Koophandel kunnen in het bezit komen van een Sligro-klantenkaart. Deze klantenkaart heb je nodig om inkopen te kunnen doen bij Sligro. Het is ook mogelijk om bezorgklant te worden, je krijgt je producten dan afgeleverd op het afgesproken bezorgadres. We hanteren hiervoor een minimale orderwaarde van €500,00 op basis van periodieke levering.";

    $data = [
        "model" => "llama-3.3-70b-versatile",
        "messages" => [
            ["role" => "system", "content" => "Hola eres un experto en neerlandes y en español, traduce el siguiente mensaje sin mensajes adicionales."],
            ["role" => "user", "content" => $text]
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
        dd($decodedResponse); // Mostrar la respuesta de la API
    }

    curl_close($ch);
});