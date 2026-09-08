-- CreateEnum
CREATE TYPE "ShiftAvailability" AS ENUM ('AVAILABLE', 'FEW', 'SOLD_OUT');

-- AlterTable
ALTER TABLE "Tour" ADD COLUMN     "archivedAt" TIMESTAMP(3),
ADD COLUMN     "internalNote" TEXT;

-- AlterTable
ALTER TABLE "TourShift" ADD COLUMN     "availability" "ShiftAvailability" NOT NULL DEFAULT 'AVAILABLE',
ADD COLUMN     "seatsLeft" INTEGER;

-- CreateIndex
CREATE INDEX "TourShift_startDate_idx" ON "TourShift"("startDate");
