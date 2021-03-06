<?php

require_once 'extension/owscriptlogger/classes/owscriptlogger_signalhandler.php';

class OWScriptLogger extends eZPersistentObject {

    const DEBUGLOG = 'debug';
    const HIGHLIGHTNOTICELOG = 'h_notice';
    const NOTICELOG = 'notice';
    const ERRORLOG = 'error';
    const WARNINGLOG = 'warning';
    const RUNNING_STATUS = 'running';
    const FINISHED_STATUS = 'finished';
    const ERROR_STATUS = 'error';
    const STOPED_STATUS = 'manually_stoped';

    protected $_errorLogFile = 'owscriptlogger-error.log';
    protected $_warningLogFile = 'owscriptlogger-warning.log';
    protected $_noticeLogFile = 'owscriptlogger-notice.log';
    protected $_debugLogFile = 'owscriptlogger-debug.log';
    protected $_allowedDatabaseDebugLevel = self::DEBUGLOG;
    protected $_noDBLogActions = array();
    protected static $_timer;
    protected static $_cli;
    protected static $_instance;
    public static $_storeObjectInDB = FALSE;

    static function instance() {
        if ( !self::$_instance ) {
            throw new Exception( "OWScriptLogger instance not found. Call startLog() method before starting to log messages." );
        }
        return self::$_instance;
    }

    public static function startLog( $logIdentifier ) {
        $trans = eZCharTransform::instance();
        $logIdentifier = strtolower( preg_replace( '/([A-Z])/', '_$1', $logIdentifier ) );
        $logIdentifier = $trans->transformByGroup( $logIdentifier, 'identifier' );
        $logger = new OWScriptLogger( $logIdentifier );
        $logger->_allowedDatabaseDebugLevel = $logger->attribute( 'script' )->attribute( 'database_log_level' );
        $logger->_noDBLogActions = $logger->attribute( 'script' )->attribute( 'no_db_log_action_list' );
        $cli = eZCLI::instance();
        if ( !$cli->isWebOutput() ) {
            eZDebug::setHandleType( eZDebug::HANDLE_TO_PHP );
            set_error_handler( 'OWScriptLoggerNoticeError', E_NOTICE | E_USER_NOTICE );
            set_error_handler( 'OWScriptLoggerWarningError', E_WARNING | E_USER_WARNING );
        }
        eZExecution::addFatalErrorHandler( 'OWScriptLoggerFatalError' );
        eZExecution::addCleanupHandler( 'OWScriptLoggerCleanupHandler' );
        set_exception_handler( 'OWScriptLoggerExceptionHandler' );
        self::$_instance = $logger;
        OWScriptLogger::$_timer = new ezcDebugTimer( );
        OWScriptLogger::$_timer->startTimer( $logger->attribute( 'identifier' ), 'OWScriptLogger' );
    }

    public static function setAllowedDatabaseDebugLevel( $level ) {
        try {
            $logger = self::instance();
            $logger->_allowedDatabaseDebugLevel = $level;
        } catch ( Exception $e ) {
            
        }
    }

    public static function logMessage( $msg, $action = 'undefined', $highlight = false, $bPrintMsg = true, $logType = self::NOTICELOG ) {
        $storeInDatabase = FALSE;
        try {
            $logger = self::instance();
        } catch ( Exception $e ) {
            
        }
        $trans = eZCharTransform::instance();
        $action = strtolower( preg_replace( '/([A-Z])/', '_$1', $action ) );
        $action = $trans->transformByGroup( $action, 'identifier' );
        switch ( $logType ) {
            case self::ERRORLOG :
                if ( isset( $logger ) ) {
                    if ( $logger->_allowedDatabaseDebugLevel == self::DEBUGLOG || $logger->_allowedDatabaseDebugLevel == self::NOTICELOG || $logger->_allowedDatabaseDebugLevel == self::WARNINGLOG || $logger->_allowedDatabaseDebugLevel == self::ERRORLOG ) {
                        $storeInDatabase = TRUE;
                    }
                    $logFile = $logger->_errorLogFile;
                } else {
                    $logFile = 'owscriptlogger-error.log';
                }
                if ( $bPrintMsg ) {
                    self::writeError( $msg, $action, $highlight );
                }
                break;

            case self::WARNINGLOG :
                if ( isset( $logger ) ) {
                    if ( $logger->_allowedDatabaseDebugLevel == self::DEBUGLOG || $logger->_allowedDatabaseDebugLevel == self::NOTICELOG || $logger->_allowedDatabaseDebugLevel == self::WARNINGLOG ) {
                        $storeInDatabase = TRUE;
                    }
                    $logFile = $logger->_warningLogFile;
                } else {
                    $logFile = 'owscriptlogger-warning.log';
                }
                if ( $bPrintMsg ) {
                    self::writeWarning( $msg, $action, $highlight );
                }
                break;

            case self::NOTICELOG :
                if ( isset( $logger ) ) {
                    if ( $logger->_allowedDatabaseDebugLevel == self::DEBUGLOG || $logger->_allowedDatabaseDebugLevel == self::NOTICELOG ) {
                        $storeInDatabase = TRUE;
                    }
                    $logFile = $logger->_noticeLogFile;
                } else {
                    $logFile = 'owscriptlogger-notice.log';
                }
                if ( $bPrintMsg ) {
                    self::writeNotice( $msg, $action, $highlight );
                }
                break;

            case self::DEBUGLOG :
                if ( isset( $logger ) ) {
                    if ( $logger->_allowedDatabaseDebugLevel == self::DEBUGLOG ) {
                        $storeInDatabase = TRUE;
                    }
                    $logFile = $logger->_debugLogFile;
                } else {
                    $logFile = 'owscriptlogger-debug.log';
                }
                if ( $bPrintMsg ) {
                    self::writeDebug( $msg, $action, $highlight );
                }
                break;
        }
        if ( !isset( $logger ) || in_array( $action, $logger->_noDBLogActions ) ) {
            $storeInDatabase = FALSE;
        }
        if ( $storeInDatabase ) {
            $logger->store();
            OWScriptLogger::$_storeObjectInDB = TRUE;
            $row = array(
                'owscriptlogger_id' => $logger->attribute( 'id' ),
                'date' => date( 'Y-m-d H:i:s' ),
                'level' => $logType,
                'action' => $action,
                'message' => $msg
            );
            OWScriptLogger_Log::create( $row );
        }
        if ( isset( $logFile ) ) {
            eZLog::write( $msg, $logFile );
        }
    }

    public static function logDebug( $msg, $action = 'undefined', $highlight = false, $bPrintMsg = true ) {
        self::logMessage( $msg, $action, $highlight, $bPrintMsg, self::DEBUGLOG );
    }

    public static function logNotice( $msg, $action = 'undefined', $highlight = false, $bPrintMsg = true ) {
        self::logMessage( $msg, $action, $highlight, $bPrintMsg, self::NOTICELOG );
    }

    public static function logWarning( $msg, $action = 'undefined', $highlight = false, $bPrintMsg = true ) {
        self::logMessage( $msg, $action, $highlight, $bPrintMsg, self::WARNINGLOG );
    }

    public static function logError( $msg, $action = 'undefined', $highlight = false, $bPrintMsg = true ) {
        self::logMessage( $msg, $action, $highlight, $bPrintMsg, self::ERRORLOG );
    }

    public static function writeMessage( $msg, $action = 'undefined', $highlight = false, $logType = self::NOTICELOG ) {
        try {
            $logger = self::instance();
            $label = $logger->attribute( 'identifier' );
        } catch ( Exception $e ) {
            $label = 'OWScriptLogger';
        }
        self::$_cli = eZCLI::instance();
        $isWebOutput = self::$_cli->isWebOutput();
        $msg = $action . '::' . $msg;
        switch ( $logType ) {
            case self::ERRORLOG :
                if ( !$isWebOutput ) {
                    self::cliError( $msg, $highlight );
                } else {
                    eZDebug::writeError( $msg, $label );
                }
                break;

            case self::WARNINGLOG :
                if ( !$isWebOutput ) {
                    self::cliWarning( $msg, $highlight );
                } else {
                    eZDebug::writeWarning( $msg, $label );
                }
                break;

            case self::NOTICELOG :
            default :
                if ( !$isWebOutput ) {
                    self::cliNotice( $msg, $highlight );
                } else {
                    eZDebug::writeNotice( $msg, $label );
                }
                break;

            case self::DEBUGLOG :
            default :
                if ( !$isWebOutput ) {
                    self::cliDebug( $msg, $highlight );
                } else {
                    eZDebug::writeDebug( $msg, $label );
                }
                break;
        }
    }

    public static function writeError( $msg, $action = 'undefined', $highlight = false ) {
        self::writeMessage( $msg, $action, $highlight, self::ERRORLOG );
    }

    public static function writeWarning( $msg, $action = 'undefined', $highlight = false ) {
        self::writeMessage( $msg, $action, $highlight, self::WARNINGLOG );
    }

    public static function writeNotice( $msg, $action = 'undefined', $highlight = false ) {
        self::writeMessage( $msg, $action, $highlight, self::NOTICELOG );
    }

    public static function writeDebug( $msg, $action = 'undefined', $highlight = false ) {
        self::writeMessage( $msg, $action, $highlight, self::DEBUGLOG );
    }

    public static function cliError( $string = false, $highlight = false ) {
        self::$_cli = eZCLI::instance();
        self::$_cli->TerminalStyles['red'] = "\033[1;49;91m";
        self::$_cli->TerminalStyles['red-bg'] = "\033[7;49;91m";
        $style = $highlight ? 'red-bg' : 'red';
        $string = self::$_cli->stylize( $style, $string );
        fputs( STDERR, $string );
        fputs( STDERR, self::$_cli->endlineString() );
    }

    public static function cliWarning( $string = false, $highlight = false ) {
        self::$_cli = eZCLI::instance();
        self::$_cli->TerminalStyles['yellow'] = "\033[1;49;93m";
        self::$_cli->TerminalStyles['yellow-bg'] = "\033[7;49;93m";
        $style = $highlight ? 'yellow-bg' : 'yellow';
        $string = self::$_cli->stylize( $style, $string );
        fputs( STDERR, $string );
        fputs( STDERR, self::$_cli->endlineString() );
    }

    public static function cliNotice( $string = false, $highlight = false ) {
        self::$_cli = eZCLI::instance();
        self::$_cli->TerminalStyles['green'] = "\033[1;49;32m";
        self::$_cli->TerminalStyles['green-bg'] = "\033[7;49;92m";
        $style = $highlight ? 'green-bg' : 'green';
        $string = self::$_cli->stylize( $style, $string );
        fputs( STDERR, $string );
        fputs( STDERR, self::$_cli->endlineString() );
    }

    public static function cliDebug( $string = false, $highlight = false ) {
        self::$_cli = eZCLI::instance();
        self::$_cli->TerminalStyles['white'] = "\033[1;49;39m";
        self::$_cli->TerminalStyles['white-bg'] = "\033[7;49;39m";
        $style = $highlight ? 'white-bg' : 'white';
        $string = self::$_cli->stylize( $style, $string );
        fputs( STDERR, $string );
        fputs( STDERR, self::$_cli->endlineString() );
    }

    /* eZPersistentObject methods */

    public static function definition() {
        return array(
            'fields' => array(
                'id' => array(
                    'name' => 'id',
                    'datatype' => 'integer',
                ),
                'identifier' => array(
                    'name' => 'old_identifier',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => true
                ),
                'owscriptlogger_script_id' => array(
                    'name' => 'owscriptlogger_script_id',
                    'datatype' => 'integer',
                    'default' => null,
                    'required' => true
                ),
                'date' => array(
                    'name' => 'date',
                    'datatype' => 'string',
                    'default' => null,
                    'required' => true
                ),
                'runtime' => array(
                    'name' => 'runtime',
                    'datatype' => 'float',
                    'default' => null,
                    'required' => false
                ),
                'memory_usage' => array(
                    'name' => 'memory_usage',
                    'datatype' => 'integer',
                    'default' => null,
                    'required' => false
                ),
                'memory_usage_peak' => array(
                    'name' => 'memory_usage_peak',
                    'datatype' => 'integer',
                    'default' => null,
                    'required' => false
                ),
                'notice_count' => array(
                    'name' => 'notice_count',
                    'datatype' => 'integer',
                    'default' => 0,
                    'required' => false
                ),
                'warning_count' => array(
                    'name' => 'warning_count',
                    'datatype' => 'integer',
                    'default' => 0,
                    'required' => false
                ),
                'error_count' => array(
                    'name' => 'error_count',
                    'datatype' => 'integer',
                    'default' => 0,
                    'required' => false
                ),
                'status' => array(
                    'name' => 'status',
                    'datatype' => 'string',
                    'default' => self::RUNNING_STATUS,
                    'required' => true
                ),
            ),
            'keys' => array(
                'identifier',
                'date'
            ),
            'increment_key' => 'id',
            'sort' => array( 'date' => 'asc' ),
            'grouping' => array(),
            'class_name' => 'OWScriptLogger',
            'name' => 'owscriptlogger',
            'function_attributes' => array(
                'logs' => 'getLogs',
                'actions' => 'getActions',
                'identifier' => 'getIdentifier',
                'script' => 'getScript'
            ),
            'set_functions' => array()
        );
    }

    public function __construct( $identifier_or_row ) {
        $row = array(
            'id' => NULL,
            'owscriptlogger_script_id' => 0,
            'date' => date( 'Y-m-d H:i:s' ),
            'runtime' => NULL,
            'memory_usage' => NULL,
            'memory_usage_peak' => NULL
        );
        if ( is_array( $identifier_or_row ) ) {
            $row = array_merge( $row, $identifier_or_row );
        } else {
            $row['identifier'] = $identifier_or_row;
        }
        if ( isset( $row['identifier'] ) ) {
            $identifier = $row['identifier'];
            unset( $row['identifier'] );
            if ( $row['owscriptlogger_script_id'] == 0 ) {
                $trans = eZCharTransform::instance();
                $identifier = $trans->transformByGroup( $identifier, 'identifier' );
                $script = OWScriptLogger_Script::findOrCreate( $identifier );
                $row['owscriptlogger_script_id'] = $script->attribute( 'id' );
                $saveRow = TRUE;
            }
            parent::__construct( $row );
            $this->_errorLogFile = $identifier . '-error.log';
            $this->_warningLogFile = $identifier . '-warning.log';
            $this->_noticeLogFile = $identifier . '-notice.log';
            $this->_debugLogFile = $identifier . '-debug.log';
        } else {
            throw new OWScriptLoggerException( __METHOD__ . " : Script logger identifier must be set" );
        }
    }

    public function storeExtraInfo() {
        if ( OWScriptLogger::$_storeObjectInDB ) {
            $this->setAttribute( 'notice_count', $this->countNotice() );
            $this->setAttribute( 'warning_count', $this->countWarning() );
            $this->setAttribute( 'error_count', $this->countError() );
            $this->setAttribute( 'memory_usage_peak', memory_get_peak_usage() );
            $this->setAttribute( 'memory_usage', memory_get_usage() );
            OWScriptLogger::$_timer->stopTimer( $this->attribute( 'identifier' ) );
            $timeData = OWScriptLogger::$_timer->getTimeData();
            if($timeData && is_object($timeData[0])) {
                $this->setAttribute( 'runtime', $timeData[0]->elapsedTime );
            }
            $this->store();
        }
    }

    public function __destruct() {
        if ( OWScriptLogger::$_timer instanceof ezcDebugTimer && OWScriptLogger::$_storeObjectInDB ) {
            $this->storeExtraInfo();
            if ( $this->attribute( 'status' ) == self::RUNNING_STATUS ) {
                $this->setAttribute( 'status', self::FINISHED_STATUS );
                $this->store();
            }
        }
    }

    public function getLogs() {
        return OWScriptLogger_Log::fetchList( array( 'owscriptlogger_id' => $this->attribute( 'id' ) ) );
    }

    public function getActions() {
        return OWScriptLogger_Log::fetchActionList( array( 'owscriptlogger_id' => $this->attribute( 'id' ) ) );
    }

    public function getIdentifier() {
        $scriptID = $this->attribute( 'owscriptlogger_script_id' );
        if ( empty( $scriptID ) ) {
            $script = OWScriptLogger_Script::findOrCreate( $this->attribute( 'old_identifier' ) );
            $this->setAttribute( 'owscriptlogger_script_id', $script->attribute( 'id' ) );
            $this->store();
        } else {
            $script = $this->attribute( 'script' );
        }
        return $script->attribute( 'identifier' );
    }

    public function getScript() {
        return OWScriptLogger_Script::fetch( $this->attribute( 'owscriptlogger_script_id' ) );
    }

    public function countNotice() {
        return $this->countLog( self::NOTICELOG );
    }

    public function countWarning() {
        return $this->countLog( self::WARNINGLOG );
    }

    public function countError() {
        return $this->countLog( self::ERRORLOG );
    }

    public function countLog( $level ) {
        return OWScriptLogger_Log::countLog( array(
                'owscriptlogger_id' => $this->attribute( 'id' ),
                'level' => $level
            ) );
    }

    public function sendFatalErrorMessage( $msg ) {
        $recipients = $this->attribute( 'script' )->attribute( 'fatal_error_recipients' );

        $recipients = explode( PHP_EOL, $recipients );
        if ( !empty( $recipients ) ) {
            if ( is_callable( 'eZTemplate::factory' ) ) {
                $tpl = eZTemplate::factory();
            } else {
                include_once ('kernel/common/template.php');
                $tpl = templateInit();
            }
            $tpl->setVariable( 'message', $msg );
            $tpl->setVariable( 'script_identifier', $this->attribute( 'identifier' ) );
            $templateResult = $tpl->fetch( 'design:owscriptlogger/mail/fatal_error.tpl' );

            $subject = $tpl->variable( 'subject' );

            $ini = eZINI::instance();
            $mail = new eZMail( );

            if ( $tpl->hasVariable( 'content_type' ) ) {
                $mail->setContentType( $tpl->variable( 'content_type' ) );
            }

            $receiver = trim( array_shift( $recipients ) );
            if ( !$mail->validate( $receiver ) ) {
                $receiver = $ini->variable( "MailSettings", "AdminEmail" );
            }
            $mail->setReceiver( $receiver );

            $ccReceivers = $recipients;
            if ( !empty( $ccReceivers ) ) {
                if ( !is_array( $ccReceivers ) ) {
                    $ccReceivers = array( $ccReceivers );
                }
                foreach ( $ccReceivers as $ccReceiver ) {
                    if ( $mail->validate( $ccReceiver ) ) {
                        $mail->addCc( $ccReceiver );
                    }
                }
            }

            $sender = $ini->variable( "MailSettings", "EmailSender" );
            $mail->setSender( $sender );

            $replyTo = $ini->variable( "MailSettings", "EmailReplyTo" );
            if ( !$mail->validate( $replyTo ) ) {
                $replyTo = $sender;
            }
            $mail->setReplyTo( $replyTo );

            $mail->setSubject( $subject );
            $mail->setBody( $templateResult );
            eZMailTransport::send( $mail );
        }
    }

    static function fetchList( $conds = array(), $order = 'asc', $limit = NULL ) {
        return self::fetchObjectList( self::definition(), null, $conds, array( 'date' => $order, ), $limit, true, false, null, null, null );
    }

    static function fetch( $id ) {
        $conds = array( 'id' => $id );
        return self::fetchObject( self::definition(), null, $conds, array( 'date' => 'asc', ) );
    }

    static function fetchByIdentiferAndDate( $identifier, $date ) {
        $conds = array(
            'identifier' => $identifier,
            'date' => $date
        );
        return self::fetchObject( self::definition(), null, $conds, array( 'date' => 'asc', ) );
    }

    static function fetchIdentifierList( $conds = array(), $limit = NULL ) {
        $identifierList = self::fetchObjectList( self::definition(), array( 'identifier' ), $conds, null, $limit, false, array(
                'identifier' ), null, null, null );
        if ( is_array( $identifierList ) ) {
            foreach ( $identifierList as $key => $item ) {
                $identifierList[$key] = $item['identifier'];
            }
        }
        return $identifierList;
    }

    static function removeList( $IDList ) {
        OWScriptLogger_Log::removeByOWScriptLoggerId( $IDList );
        $conds = array( 'id' => array( $IDList ) );
        return self::removeObject( self::definition(), $conds );
    }

    static function cleanup() {
        $custom_fields = array(
            array(
                'operation' => 'owscriptlogger.id',
                'name' => 'id'
            ),
            array(
                'operation' => 'owscriptlogger.identifier',
                'name' => 'identifier'
            ),
            array(
                'operation' => 'owscriptlogger.owscriptlogger_script_id',
                'name' => 'owscriptlogger_script_id'
            ),
            array(
                'operation' => 'owscriptlogger.date',
                'name' => 'date'
            ),
            array(
                'operation' => 'owscriptlogger.runtime',
                'name' => 'runtime'
            ),
            array(
                'operation' => 'owscriptlogger.memory_usage',
                'name' => 'memory_usage'
            ),
            array(
                'operation' => 'owscriptlogger.memory_usage_peak',
                'name' => 'memory_usage_peak'
            ),
            array(
                'operation' => 'owscriptlogger.notice_count',
                'name' => 'notice_count'
            ),
            array(
                'operation' => 'owscriptlogger.warning_count',
                'name' => 'warning_count'
            ),
            array(
                'operation' => 'owscriptlogger.error_count',
                'name' => 'error_count'
            ),
            array(
                'operation' => 'owscriptlogger.status',
                'name' => 'status'
            )
        );

        $custom_tables = array( "owscriptlogger_script" );

        $custom_conds = " WHERE owscriptlogger.owscriptlogger_script_id = owscriptlogger_script.id ";
        $custom_conds .= " AND (";
        $custom_conds .= "( owscriptlogger_script.max_age_finished != 0 AND owscriptlogger.date < DATE_SUB(NOW(),INTERVAL owscriptlogger_script.max_age_finished DAY) AND owscriptlogger.status = 'finished' ) ";
        $custom_conds .= " OR ";
        $custom_conds .= "( owscriptlogger_script.max_age_error != 0 AND owscriptlogger.date < DATE_SUB(NOW(),INTERVAL owscriptlogger_script.max_age_error DAY) AND owscriptlogger.status = 'error' ) ";
        $custom_conds .= " OR ";
        $custom_conds .= "( owscriptlogger_script.max_age_manually_stoped != 0 AND owscriptlogger.date < DATE_SUB(NOW(),INTERVAL owscriptlogger_script.max_age_manually_stoped DAY) AND owscriptlogger.status = 'manually_stoped' ) ";
        $custom_conds .= ")";

        foreach ( self::fetchObjectList( self::definition(), array(), null, null, null, true, false, $custom_fields, $custom_tables, $custom_conds ) as $object ) {
            $object->remove();
        }
    }

}
