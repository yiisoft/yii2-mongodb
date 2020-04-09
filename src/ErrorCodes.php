<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\mongodb;

use MongoDB\Driver\Exception\Exception as MongoDBExceptionInterface;
use yii\mongodb\Exception as YiiMongoDBException;

/**
 * All errors code in mongodb. generated from https://github.com/mongodb/mongo/blob/master/src/mongo/base/error_codes.yml
 * @author Abolfazl Ziaratban <abolfazl.ziaratban@gmail.com>
*/

class ErrorCodes {

    const OK = 0;
    const InternalError = 1;
    const BadValue = 2;
    const OBSOLETE_DuplicateKey = 3;
    const NoSuchKey = 4;
    const GraphContainsCycle = 5;
    const HostUnreachable = 6;
    const HostNotFound = 7;
    const UnknownError = 8;
    const FailedToParse = 9;
    const CannotMutateObject = 10;
    const UserNotFound = 11;
    const UnsupportedFormat = 12;
    const Unauthorized = 13;
    const TypeMismatch = 14;
    const Overflow = 15;
    const InvalidLength = 16;
    const ProtocolError = 17;
    const AuthenticationFailed = 18;
    const CannotReuseObject = 19;
    const IllegalOperation = 20;
    const EmptyArrayOperation = 21;
    const InvalidBSON = 22;
    const AlreadyInitialized = 23;
    const LockTimeout = 24;
    const RemoteValidationError = 25;
    const NamespaceNotFound = 26;
    const IndexNotFound = 27;
    const PathNotViable = 28;
    const NonExistentPath = 29;
    const InvalidPath = 30;
    const RoleNotFound = 31;
    const RolesNotRelated = 32;
    const PrivilegeNotFound = 33;
    const CannotBackfillArray = 34;
    const UserModificationFailed = 35;
    const RemoteChangeDetected = 36;
    const FileRenameFailed = 37;
    const FileNotOpen = 38;
    const FileStreamFailed = 39;
    const ConflictingUpdateOperators = 40;
    const FileAlreadyOpen = 41;
    const LogWriteFailed = 42;
    const CursorNotFound = 43;
    const UserDataInconsistent = 45;
    const LockBusy = 46;
    const NoMatchingDocument = 47;
    const NamespaceExists = 48;
    const InvalidRoleModification = 49;
    const MaxTimeMSExpired = 50;
    const ManualInterventionRequired = 51;
    const DollarPrefixedFieldName = 52;
    const InvalidIdField = 53;
    const NotSingleValueField = 54;
    const InvalidDBRef = 55;
    const EmptyFieldName = 56;
    const DottedFieldName = 57;
    const RoleModificationFailed = 58;
    const CommandNotFound = 59;
    const OBSOLETE_DatabaseNotFound = 60;
    const ShardKeyNotFound = 61;
    const OplogOperationUnsupported = 62;
    const StaleShardVersion = 63;
    const WriteConcernFailed = 64;
    const MultipleErrorsOccurred = 65;
    const ImmutableField = 66;
    const CannotCreateIndex = 67;
    const IndexAlreadyExists = 68;
    const AuthSchemaIncompatible = 69;
    const ShardNotFound = 70;
    const ReplicaSetNotFound = 71;
    const InvalidOptions = 72;
    const InvalidNamespace = 73;
    const NodeNotFound = 74;
    const WriteConcernLegacyOK = 75;
    const NoReplicationEnabled = 76;
    const OperationIncomplete = 77;
    const CommandResultSchemaViolation = 78;
    const UnknownReplWriteConcern = 79;
    const RoleDataInconsistent = 80;
    const NoMatchParseContext = 81;
    const NoProgressMade = 82;
    const RemoteResultsUnavailable = 83;
    const DuplicateKeyValue = 84;
    const IndexOptionsConflict = 85;
    const IndexKeySpecsConflict = 86;
    const CannotSplit = 87;
    const SplitFailed_OBSOLETE = 88;
    const NetworkTimeout = 89;
    const CallbackCanceled = 90;
    const ShutdownInProgress = 91;
    const SecondaryAheadOfPrimary = 92;
    const InvalidReplicaSetConfig = 93;
    const NotYetInitialized = 94;
    const NotSecondary = 95;
    const OperationFailed = 96;
    const NoProjectionFound = 97;
    const DBPathInUse = 98;
    const UnsatisfiableWriteConcern = 100;
    const OutdatedClient = 101;
    const IncompatibleAuditMetadata = 102;
    const NewReplicaSetConfigurationIncompatible = 103;
    const NodeNotElectable = 104;
    const IncompatibleShardingMetadata = 105;
    const DistributedClockSkewed = 106;
    const LockFailed = 107;
    const InconsistentReplicaSetNames = 108;
    const ConfigurationInProgress = 109;
    const CannotInitializeNodeWithData = 110;
    const NotExactValueField = 111;
    const WriteConflict = 112;
    const InitialSyncFailure = 113;
    const InitialSyncOplogSourceMissing = 114;
    const CommandNotSupported = 115;
    const DocTooLargeForCapped = 116;
    const ConflictingOperationInProgress = 117;
    const NamespaceNotSharded = 118;
    const InvalidSyncSource = 119;
    const OplogStartMissing = 120;
    const DocumentValidationFailure = 121;
    const OBSOLETE_ReadAfterOptimeTimeout = 122;
    const NotAReplicaSet = 123;
    const IncompatibleElectionProtocol = 124;
    const CommandFailed = 125;
    const RPCProtocolNegotiationFailed = 126;
    const UnrecoverableRollbackError = 127;
    const LockNotFound = 128;
    const LockStateChangeFailed = 129;
    const SymbolNotFound = 130;
    const RLPInitializationFailed = 131; # Removed in 4.2
    const OBSOLETE_ConfigServersInconsistent = 132;
    const FailedToSatisfyReadPreference = 133;
    const ReadConcernMajorityNotAvailableYet = 134;
    const StaleTerm = 135;
    const CappedPositionLost = 136;
    const IncompatibleShardingConfigVersion = 137;
    const RemoteOplogStale = 138;
    const JSInterpreterFailure = 139;
    const InvalidSSLConfiguration = 140;
    const SSLHandshakeFailed = 141;
    const JSUncatchableError = 142;
    const CursorInUse = 143;
    const IncompatibleCatalogManager = 144;
    const PooledConnectionsDropped = 145;
    const ExceededMemoryLimit = 146;
    const ZLibError = 147;
    const ReadConcernMajorityNotEnabled = 148;
    const NoConfigMaster = 149;
    const StaleEpoch = 150;
    const OperationCannotBeBatched = 151;
    const OplogOutOfOrder = 152;
    const ChunkTooBig = 153;
    const InconsistentShardIdentity = 154;
    const CannotApplyOplogWhilePrimary = 155;
    const OBSOLETE_NeedsDocumentMove = 156;
    const CanRepairToDowngrade = 157;
    const MustUpgrade = 158;
    const DurationOverflow = 159;
    const MaxStalenessOutOfRange = 160;
    const IncompatibleCollationVersion = 161;
    const CollectionIsEmpty = 162;
    const ZoneStillInUse = 163;
    const InitialSyncActive = 164;
    const ViewDepthLimitExceeded = 165;
    const CommandNotSupportedOnView = 166;
    const OptionNotSupportedOnView = 167;
    const InvalidPipelineOperator = 168;
    const CommandOnShardedViewNotSupportedOnMongod = 169;
    const TooManyMatchingDocuments = 170;
    const CannotIndexParallelArrays = 171;
    const TransportSessionClosed = 172;
    const TransportSessionNotFound = 173;
    const TransportSessionUnknown = 174;
    const QueryPlanKilled = 175;
    const FileOpenFailed = 176;
    const ZoneNotFound = 177;
    const RangeOverlapConflict = 178;
    const WindowsPdhError = 179;
    const BadPerfCounterPath = 180;
    const AmbiguousIndexKeyPattern = 181;
    const InvalidViewDefinition = 182;
    const ClientMetadataMissingField = 183;
    const ClientMetadataAppNameTooLarge = 184;
    const ClientMetadataDocumentTooLarge = 185;
    const ClientMetadataCannotBeMutated = 186;
    const LinearizableReadConcernError = 187;
    const IncompatibleServerVersion = 188;
    const PrimarySteppedDown = 189;
    const MasterSlaveConnectionFailure = 190;
    const OBSOLETE_BalancerLostDistributedLock = 191;
    const FailPointEnabled = 192;
    const NoShardingEnabled = 193;
    const BalancerInterrupted = 194;
    const ViewPipelineMaxSizeExceeded = 195;
    const InvalidIndexSpecificationOption = 197;
    const OBSOLETE_ReceivedOpReplyMessage = 198;
    const ReplicaSetMonitorRemoved = 199;
    const ChunkRangeCleanupPending = 200;
    const CannotBuildIndexKeys = 201;
    const NetworkInterfaceExceededTimeLimit = 202;
    const ShardingStateNotInitialized = 203;
    const TimeProofMismatch = 204;
    const ClusterTimeFailsRateLimiter = 205;
    const NoSuchSession = 206;
    const InvalidUUID = 207;
    const TooManyLocks = 208;
    const StaleClusterTime = 209;
    const CannotVerifyAndSignLogicalTime = 210;
    const KeyNotFound = 211;
    const IncompatibleRollbackAlgorithm = 212;
    const DuplicateSession = 213;
    const AuthenticationRestrictionUnmet = 214;
    const DatabaseDropPending = 215;
    const ElectionInProgress = 216;
    const IncompleteTransactionHistory = 217;
    const UpdateOperationFailed = 218;
    const FTDCPathNotSet = 219;
    const FTDCPathAlreadySet = 220;
    const IndexModified = 221;
    const CloseChangeStream = 222;
    const IllegalOpMsgFlag = 223;
    const QueryFeatureNotAllowed = 224;
    const TransactionTooOld = 225;
    const AtomicityFailure = 226;
    const CannotImplicitlyCreateCollection = 227;
    const SessionTransferIncomplete = 228;
    const MustDowngrade = 229;
    const DNSHostNotFound = 230;
    const DNSProtocolError = 231;
    const MaxSubPipelineDepthExceeded = 232;
    const TooManyDocumentSequences = 233;
    const RetryChangeStream = 234;
    const InternalErrorNotSupported = 235;
    const ForTestingErrorExtraInfo = 236;
    const CursorKilled = 237;
    const NotImplemented = 238;
    const SnapshotTooOld = 239;
    const DNSRecordTypeMismatch = 240;
    const ConversionFailure = 241;
    const CannotCreateCollection = 242;
    const IncompatibleWithUpgradedServer = 243;
    const NOT_YET_AVAILABLE_TransactionAborted = 244;
    const BrokenPromise = 245;
    const SnapshotUnavailable = 246;
    const ProducerConsumerQueueBatchTooLarge = 247;
    const ProducerConsumerQueueEndClosed = 248;
    const StaleDbVersion = 249;
    const StaleChunkHistory = 250;
    const NoSuchTransaction = 251;
    const ReentrancyNotAllowed = 252;
    const FreeMonHttpInFlight = 253;
    const FreeMonHttpTemporaryFailure = 254;
    const FreeMonHttpPermanentFailure = 255;
    const TransactionCommitted = 256;
    const TransactionTooLarge = 257;
    const UnknownFeatureCompatibilityVersion = 258;
    const KeyedExecutorRetry = 259;
    const InvalidResumeToken = 260;
    const TooManyLogicalSessions = 261;
    const ExceededTimeLimit = 262;
    const OperationNotSupportedInTransaction = 263;
    const TooManyFilesOpen = 264;
    const OrphanedRangeCleanUpFailed = 265;
    const FailPointSetFailed = 266;
    const PreparedTransactionInProgress = 267;
    const CannotBackup = 268;
    const DataModifiedByRepair = 269;
    const RepairedReplicaSetNode = 270;
    const JSInterpreterFailureWithStack = 271;
    const MigrationConflict = 272;
    const ProducerConsumerQueueProducerQueueDepthExceeded = 273;
    const ProducerConsumerQueueConsumed = 274;
    const ExchangePassthrough = 275;
    const IndexBuildAborted = 276;
    const AlarmAlreadyFulfilled = 277;
    const UnsatisfiableCommitQuorum = 278;
    const ClientDisconnect = 279;
    const ChangeStreamFatalError = 280;
    const TransactionCoordinatorSteppingDown = 281;
    const TransactionCoordinatorReachedAbortDecision = 282;
    const WouldChangeOwningShard = 283;
    const ForTestingErrorExtraInfoWithExtraInfoInNamespace = 284;
    const IndexBuildAlreadyInProgress = 285;
    const ChangeStreamHistoryLost = 286;
    const TransactionCoordinatorDeadlineTaskCanceled = 287;
    const ChecksumMismatch = 288;
    const WaitForMajorityServiceEarlierOpTimeAvailable = 289;
    const TransactionExceededLifetimeLimitSeconds = 290;
    const NoQueryExecutionPlans = 291;
    const QueryExceededMemoryLimitNoDiskUseAllowed = 292;
    const InvalidSeedList = 293;
    const InvalidTopologyType = 294;
    const InvalidHeartBeatFrequency = 295;
    const TopologySetNameRequired = 296;
    const HierarchicalAcquisitionLevelViolation = 297;
    const InvalidServerType = 298;
    const OCSPCertificateStatusRevoked = 299;
    const RangeDeletionAbandonedBecauseCollectionWithUUIDDoesNotExist = 300;
    const DataCorruptionDetected = 301;
    const OCSPCertificateStatusUnknown = 302;
    const SplitHorizonChange = 303;
    const ShardInvalidatedForTargeting = 304;
    const ReadThroughCacheKeyNotFound = 305;
    const ReadThroughCacheLookupCanceled = 306;
    const RangeDeletionAbandonedBecauseTaskDocumentDoesNotExist = 307;
    const CurrentConfigNotCommittedYet = 308;
    const SocketException = 9001;
    const OBSOLETE_RecvStaleConfig = 9996;
    const CannotGrowDocumentInCappedNamespace = 10003;
    const NotMaster = 10107;
    const BSONObjectTooLarge = 10334;
    const DuplicateKey = 11000;
    const InterruptedAtShutdown = 11600;
    const Interrupted = 11601;
    const InterruptedDueToReplStateChange = 11602;
    const BackgroundOperationInProgressForDatabase = 12586;
    const BackgroundOperationInProgressForNamespace = 12587;
    const OBSOLETE_PrepareConfigsFailed = 13104;
    const MergeStageNoMatchingDocument = 13113;
    const DatabaseDifferCase = 13297;
    const StaleConfig = 13388;
    const NotMasterNoSlaveOk = 13435;
    const NotMasterOrSecondary = 13436;
    const OutOfDiskSpace = 14031;
    const OSBELETE_KeyTooLong = 17280;
    const ClientMarkedKilled = 46841;

    /**
     * Checking if instance of error exception is related to mongodb.
     * examples:
     * try{
     *     // mongodb commands ...  
     * }catch(\Exception $e){
     *     if(ErrorCodes::is($e))
     *         echo 'this is a mongodb error.';
     * }
     * 
     * try{
     *     // mongodb commands ...  
     * }catch(\Exception $e){
     *     if(ErrorCodes::is($e,ErrorCodes::WriteConflict))
     *         echo 'this is a mongodb write conflict error.';
     * }
     * @param \Exception $e instance of error exception.
     * @param int|null $verifyCode if set then returning true value if error code exists.
     * @return bool returning true value when this instance is related to mongodb.
     * when you set $verifyCode parameter then returns true value if instance is related to mongodb and error code exists.
    */
    public static function is($e, $verifyCode = null){
        if(
            ($e instanceof YiiMongoDBException && $e->getPrevious() instanceof MongoDBExceptionInterface)
                ||
            $e instanceof MongoDBExceptionInterface
        ){
            if($verifyCode !== null && $e->getCode() !== $verifyCode)
                return false;
            return true;
        }
        return false;
    }
}