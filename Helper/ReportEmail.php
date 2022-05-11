<?php
/**
 *
 * @author Géraud ISSERTES <gissertes@galilee.fr>
 * @copyright © 2017 Galilée (www.galilee.fr)
 */

namespace Galilee\ImportExportBundle\Helper;

use Galilee\ImportExportBundle\GalileeImportExportBundle;
use Pimcore\Config;
use Pimcore\Db;
use Pimcore\Log\Handler\ApplicationLoggerDb;
use Pimcore\Log\Simple as LogSimple;
use Pimcore\Mail;

class ReportEmail
{

    public static function sendResumeHtml($html, $loggerComponent, $startedAt)
    {
        $configHelper = new ConfigHelper();
        if ($emails = $configHelper->getReportRecipientEmails()) {

            $subject = $loggerComponent . ' (' . date(ConfigHelper::DATE_FORMAT, strtotime($startedAt)) . ')';

            try {
                $mail = new Mail();
                $mail->setBodyHtml($html);
                $mail->addTo(array_shift($emails));
                foreach ($emails as $email) {
                    $mail->addBcc($email);
                }
                $mail->setSubject($subject);
                $mail->send();
            } catch (\Exception $e) {
                LogSimple::log('import-export', '[' . $loggerComponent . '] ' . 'Une erreur est survenue lors de l\'envoi de l\'e-mail de notification. ' . $e->getMessage());
            }
        }

    }

    /**
     * @deprecated Use sendResumeHtml instead.
     * @param $loggerComponent
     * @param $startedAt
     * @return bool
     * @throws \Exception
     */
    public static function send($loggerComponent, $startedAt)
    {
        $logs = self::getLogs($loggerComponent, $startedAt);
        if (!$logs) {
            return false;
        }
        $txt = self::buildTxtFromLogs($logs, $loggerComponent);

        $reportFileName = GalileeImportExportBundle::REPORT_FILE_PATH . $loggerComponent
            . '_' . date(ConfigHelper::DATE_FORMAT, strtotime($startedAt))
            . '.txt';

        file_put_contents($reportFileName, $txt);
        $configHelper = new ConfigHelper();

        if ($emails = $configHelper->getReportRecipientEmails()) {

            try {
                $mail = new Mail();

                if (file_exists($reportFileName) && filesize($reportFileName) < (5 * 1024 * 1024)) {
                    $mail->setBodyText("La taille du fichier de log est trop importante pour l'intégrer dans l'e-mail");
                } else {
                    $mail->setBodyHtml($txt);
                }
                $mail->addTo(array_shift($emails));
                foreach ($emails as $email) {
                    $mail->addBcc($email);
                }
                $mail->setSubject($loggerComponent . ' (' . date(ConfigHelper::DATE_FORMAT, strtotime($startedAt)) . ')');
                $mail->send();
            } catch (\Exception $e) {
                LogSimple::log('import-export', '[' . $loggerComponent . '] ' . 'Une erreur est survenue lors de l\'envoi de l\'e-mail de notification. ' . $e->getMessage());
            }
        }

        return true;

    }

    public static function getLogs($loggerComponent, $startedAt, $msg = '')
    {
        $connection = Db::get();
        $query[] = 'SELECT * FROM ' . ApplicationLoggerDb::TABLE_NAME;
        $query[] = 'WHERE timestamp >= ' . $connection->quote($startedAt);
        $query[] = 'AND component = ' . $connection->quote($loggerComponent);
        if ($msg) {
            $query[] = ' AND message LIKE ' . $connection->quote('%' . $msg . '%');
        }

        return $connection->fetchAll(implode(' ', $query));
    }

    protected static function buildTxtFromLogs($logs, $loggerComponent)
    {
        $line = $loggerComponent . PHP_EOL;
        $line .= '-----------------' . PHP_EOL;
        foreach ($logs as $row) {

            switch ($row['priority']) {
                case 'info':
                    $line .= $row['message'] . PHP_EOL;
                    break;
                case 'warning':
                    $line .= '[WARNING] ' . $row['message'] . PHP_EOL;
                    break;
                case 'error':
                    $line .= '[ERROR] ' . $row['message'] . PHP_EOL;
                    break;
                default:
                    $line .= $row['message'] . PHP_EOL;
            }
            $line .= PHP_EOL;
        }
        return $line;
    }


    protected static function buildHtmlFromLogs($logs, $loggerComponent)
    {
        $html = '<h1>' . $loggerComponent . '</h1>';
        $html .= '<ul>';
        foreach ($logs as $row) {
            $html .= '<li>';
            switch ($row['priority']) {
                case 'info':
                    $html .= '<p>' . $row['message'] . '</p>';
                    break;
                case 'warning':
                    $html .= '<p style="color:orange">' . $row['message'] . '</p>';
                    break;
                case 'error':
                    $html .= '<p style="color:red">' . $row['message'] . '</p>';
                    break;
                default:
                    $html .= '<p style="color:darkgray">' . $row['message'] . '</p>';
            }
            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }
}
