<?php

namespace App\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
// use Symfony\Component\BrowserKit\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_go')]
    public function go(): Response
    {
        return $this->render('main/go.html.twig', []);
    }


    #[Route('/result', name: 'app_result')]
    public function result(Request $request): Response
    {
        $url = $request->getPayload()->get('url');
        $error = false;

        $content = '';

        if (empty($url)) {
            $error = true;
            $errorMessage = 'Указано пустое поле URL';
        } else {

            $urlParts = parse_url($url);

            if (isset($urlParts['scheme']) == false || isset($urlParts['host']) == false) {
                $error = true;
                $errorMessage = 'Not valid url structure';
            }
        }

        try {
            $content = file_get_contents($url);
        } catch (Exception $e) {
            $error = true;
            $errorMessage = 'Content error: ' . $e->getMessage();
        }

        if ($error == true) {
            return $this->render('main/result.html.twig', [
                'url' => $url,
                'errorMessage' => $errorMessage
            ]);
        }

        $regex = '/(\=[\S]+\.(jpg|jpeg|svg|png|bmp|gif|webp)\s)|(\"[\S][^"]+\.(jpg|jpeg|svg|png|bmp|gif|webp)\")|(\'[\S][^\']+\.(jpg|jpeg|svg|png|bmp|gif|webp)\')|(url\([\S]+\.(jpg|jpeg|svg|png|bmp|gif|webp)\))/';

        $stringMatches = [];
        $imagesList = [];
        $resultData = [];

        preg_match_all(
            $regex,
            $content,
            $stringMatches
        );


        if (count($stringMatches) > 0 && count($stringMatches[0]) > 0) {

            foreach ($stringMatches[0] as $key => $val) {
                $finalPath = '';

                // Исключаем unicode-escape
                $validate = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UTF-16BE');
                }, $val);


                // Избавляемся от последствий работы с регулярными выражениями
                $validate = str_replace(
                    ["url", '(', ')', '=', "\"", "'"],
                    ['', '', '', '', '', ''],
                    $validate
                );
                $parse = parse_url($validate);

                if (isset($parse['scheme']) == false || empty($parse['scheme'])) {
                    $finalPath .= $urlParts['scheme'];
                } else {
                    $finalPath .= $parse['scheme'];
                }

                $finalPath .= '://';

                if (isset($parse['host']) == false || empty($parse['host'])) {
                    $finalPath .= $urlParts['host'];
                } else {
                    $finalPath .= $parse['host'];
                }

                if (isset($parse['path']) && empty($parse['path']) == false) {
                    $finalPath .= $parse['path'];
                }

                $imagesList[] = $finalPath;
            }

            array_unique($imagesList);
        }

        $finalSize = 0;
        $counter = 0;

        foreach ($imagesList as $imagePath) {
            // $resultData[] = [
            //     'imagePath' => $imagePath,            
            // ];

            $data = get_headers($imagePath, true);           

            if (isset($data['Content-Length'])) {
                $fileSize = $data['Content-Length'];
                $resultData[] = [
                    'imagePath' => $imagePath,
                    'fileSize' => $fileSize
                ];

                $counter++;
                $finalSize += $fileSize;
            }
        }

        

        return $this->render('main/result.html.twig', [
            'url' => $url,
            'resultData' => $resultData,
            'finalSize' => $finalSize,
            'counter' => $counter
        ]);
    }
}
