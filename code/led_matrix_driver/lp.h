#ifndef LP_H
#define LP_H

#define LP0_BASE 0x0378

#define LP_DATA(x) ((x) + 0x0000)
#define LP_STATUS(x) ((x) + 0x0001)
#define LP_CONTROL(x) ((x) + 0x0002)
#define LP_EPP0(x) ((x) + 0x0003)
#define LP_EPP1(x) ((x) + 0x0004)
#define LP_ECR(x) ((x) + 0x0402)


// bit map flags

#define LP_STATUS_BUSY 0x80
#define LP_STATUS_ACK 0x40
#define LP_STATUS_OUT 0x20
#define LP_STATUS_IN 0x10
#define LP_STATUS_ERR 0x08
#define LP_STATUS_IRQ 0x04

#define LP_CONTROL_BIDIR 0x20
#define LP_CONTROL_IRQENABLE 0x10
#define LP_CONTROL_SELECT 0x08
#define LP_CONTROL_RESET 0x04
#define LP_CONTROL_AUTOLF 0x02
#define LP_CONTROL_STROBE 0x01

// ecr mode is bits 5-7
#define LP_ECR_MODE 0xe0
#define LP_ECR_MODE_STANDARD 0x00
#define LP_ECR_MODE_BYTE 0x20
#define LP_ECR_MODE_CONF 0xe0

#endif

