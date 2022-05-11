<?php

namespace Galilee\ImportExportBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class ImportController
 * @package Galilee\ImportExportBundle\Controller
 * @Route("/admin-galilee-import")
 */
class ImportController extends AbstractController
{

    /**
     * @Route("/get-report-email", name="galilee_get_report_email")
     * @return Response
     * @throws \Exception
     */
    public function getReportEmailAction()
    {
        $result = [];
        $emails = $this->configHelper->getReportRecipientEmails();
        foreach ($emails as $email) {
            $result[] = array('email' => $email);
        }
        return $this->json(array('emails' => $result));
    }


    /**
     * @Route("/add-report-email", name="galilee_add_report_email")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function addReportEmailAction(Request $request)
    {
        $email = $request->query->get('email');
        $result = $this->configHelper->addReportRecipientEmails($email);
        return $result
            ? $this->json(["response" => 'OK'])
            : $this->json(["response" => 'KO']);
    }


    /**
     * @Route("/delete-report-email", name="galilee_delete_report_email")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function deleteReportEmailAction(Request $request)
    {
        $index = $request->query->get('index');
        $result = $this->configHelper->removeReportRecipientEmails($index);
        return $result
            ? $this->json(["response" => 'OK'])
            : $this->json(["response" => 'KO']);
    }
}
