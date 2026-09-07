-- CreateTable
CREATE TABLE "Deployment" (
    "id" SERIAL NOT NULL,
    "ref" TEXT NOT NULL,
    "jobId" TEXT,
    "commit" TEXT,
    "status" TEXT NOT NULL DEFAULT 'running',
    "startedBy" TEXT,
    "startedAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "finishedAt" TIMESTAMP(3),
    "log" TEXT,

    CONSTRAINT "Deployment_pkey" PRIMARY KEY ("id")
);
