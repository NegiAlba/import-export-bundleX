<?php

namespace Galilee\ImportExportBundle\Controller;

use Galilee\ImportExportBundle\GalileeImportExportBundle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Galilee\ImportExportBundle\Command\ExportCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ExportController
 * @package Galilee\ImportExportBundle\Controller
 * @Route("/admin-galilee-export")
 */
class ExportController extends AbstractController
{

    /**
     * @Route("/get-export", name="galilee_get_export")
     * @return Response
     * @throws \Exception
     */
    public function getExportAction()
    {
        $result = [];
        $lastExportDate = $this->configHelper->getLastExportDate();
        if ($lastExportDate) {
            $lastExportDate = date('d/m/Y H:i:s', strtotime($lastExportDate));
            $result = ['export' => $lastExportDate];
        }
        return $this->json(['export' => $result]);
    }

    /**
     * @Route("/create-export", name="galilee_create_export")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function createExportAction(Request $request)
    {
        $params = [
            'export_path' => GalileeImportExportBundle::EXPORT_FILE_PATH,
            '--export_date' => $request->query->get('export_date'),
            '--type'    => 'products'
        ];
        $application = new ExportCommand();
        $application->addOption('no-ansi');
        $input = new ArrayInput($params);
        $output = new ConsoleOutput();
        $application->run($input, $output);
        return $this->json(['response' => 'OK']);
    }
}
