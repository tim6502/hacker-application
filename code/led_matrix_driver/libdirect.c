
#include <signal.h>
#include <pthread.h>

#ifdef DEBUG
	#include <stdio.h>
#endif

#if DEBUG == 1
	#include "byte_bits.h"
#else
	#include "lp.h"
	#include "parport.h"
#endif

#include "libdirect.h"
#include "libmatrixdriver.h"


#define MATRIX_STROBE 0x80
//#define DELAY_MICRO_SECONDS (1 * 1000)

#define DELAY_MICRO_SECONDS (11)
#define DELAY_DELTA (10)
#define MIN_DELAY (1)

/*
#define DELAY_MICRO_SECONDS (1)
#define DELAY_DELTA (25000)
#define MIN_DELAY (1)
*/


typedef struct {
	unsigned char active_ind;

	unsigned char pending_update_ind;
	unsigned char *matrix_buffer;

	int delay_micro_seconds;
} driver_control_struct;

driver_control_struct driver_control_rec;


unsigned char active_buffer_nx;
unsigned char matrix_buffers[2][10];


pthread_t driver_thread_handle;
pthread_mutex_t driver_mutex;
pthread_cond_t driver_ack_cond;



void *driverThread(void *args) {
	unsigned char active_ind;
	unsigned char col;
	unsigned char pdata;
	unsigned char pcontrol;
	int delay_micro_seconds = DELAY_MICRO_SECONDS;
	unsigned char reset;

	active_ind = 1;
	col = 0;



#ifndef DEBUG

	parport_writeByte(0x00);
	usleep(delay_micro_seconds);

	pcontrol = parport_getControl();

	pcontrol &= ~LP_CONTROL_RESET;

	parport_setControl(pcontrol);
	usleep(delay_micro_seconds);

	pcontrol |= LP_CONTROL_RESET;

	parport_setControl(pcontrol);
	usleep(delay_micro_seconds);

	reset = 1;
#endif


	while (1) {
#if DEBUG == 1
		if (col == 0) {
			printf("\nDisplaying Matrix %d\n\n", active_buffer_nx);
		}
#endif

		pdata = matrix_buffers[active_buffer_nx][col];
		col++;

		pdata = pdata | MATRIX_STROBE;

		if (reset) {
			reset = 0;
		} else {
#if DEBUG == 1
			bytebitsShow(pdata);
#else
			//parport_writeByte(pdata);
			parport_writeByte(MATRIX_STROBE);
#endif
		}

		pdata = pdata & ~MATRIX_STROBE;

#if DEBUG == 1
		bytebitsShow(pdata);
#else
		parport_writeByte(pdata);
#endif


		pthread_mutex_lock(&driver_mutex);

		active_ind = driver_control_rec.active_ind;
		delay_micro_seconds = driver_control_rec.delay_micro_seconds;


		if (col == 10) {
			if (!active_ind) {
				pthread_mutex_unlock(&driver_mutex);
				break;
			}

			col = 0;

			if (driver_control_rec.pending_update_ind) {
				driver_control_rec.matrix_buffer = matrix_buffers[active_buffer_nx];
				active_buffer_nx = (active_buffer_nx + 1) % 2;
			}

			driver_control_rec.pending_update_ind = 0;
			pthread_cond_broadcast(&driver_ack_cond);
		}

		pthread_mutex_unlock(&driver_mutex);

		if (delay_micro_seconds > 0) {
			usleep(delay_micro_seconds);
		}
	}

#ifndef DEBUG
	parport_writeByte(0x00);
#endif

	pthread_cond_broadcast(&driver_ack_cond);

	pthread_exit(NULL);
}

void matrixDriver_update(unsigned char *mtx) {
	unsigned char i;

	pthread_mutex_lock(&driver_mutex);

	if (driver_control_rec.active_ind) {

		if (driver_control_rec.pending_update_ind) {
			pthread_cond_wait(&driver_ack_cond, &driver_mutex);
		}

		if (driver_control_rec.active_ind) {
			for (i = 0; i < 10; i++) {
				driver_control_rec.matrix_buffer[i] = mtx[i];
			}

			driver_control_rec.pending_update_ind = 1;
		}
	}

	pthread_mutex_unlock(&driver_mutex);
}


void driverInit() {
	unsigned char i;

	active_buffer_nx = 0;
	for (i = 0; i < 10; i++) {
		matrix_buffers[0][i] = 0;
		matrix_buffers[1][i] = 0;
	}

	driver_control_rec.active_ind = 1;
	driver_control_rec.pending_update_ind = 0;
	driver_control_rec.matrix_buffer = matrix_buffers[1]; // ie. the inactive matrix
	driver_control_rec.delay_micro_seconds = DELAY_MICRO_SECONDS;

#ifndef DEBUG
	parport_init();
#endif
}

void driverCleanup() {
#ifndef DEBUG
	parport_cleanup();
#endif
}



void libdirect_sigHandler(int sig) {
	if (sig == 55) {
		pthread_mutex_lock(&driver_mutex);
		if ((driver_control_rec.delay_micro_seconds - DELAY_DELTA) >= MIN_DELAY) {
			driver_control_rec.delay_micro_seconds = driver_control_rec.delay_micro_seconds - DELAY_DELTA;
		} else {
			driver_control_rec.delay_micro_seconds = MIN_DELAY;
		}
		pthread_mutex_unlock(&driver_mutex);

		signal(55, libdirect_sigHandler);
	} else if (sig == 56) {
		pthread_mutex_lock(&driver_mutex);
		driver_control_rec.delay_micro_seconds = driver_control_rec.delay_micro_seconds + DELAY_DELTA;
		pthread_mutex_unlock(&driver_mutex);

		signal(56, libdirect_sigHandler);
	}

#ifdef DEBUG
	fprintf(stderr, "Delay (ms): %d\n", driver_control_rec.delay_micro_seconds);
#endif
}


void matrixDriver_init() {
	unsigned int rc;
	void *status;

	pthread_attr_t attr;


	signal(55, libdirect_sigHandler);
	signal(56, libdirect_sigHandler);


	driverInit();

	pthread_mutex_init(&driver_mutex, NULL);
	pthread_cond_init(&driver_ack_cond, NULL);

	pthread_attr_init(&attr);
	pthread_attr_setdetachstate(&attr, PTHREAD_CREATE_JOINABLE);

	rc = pthread_create(&driver_thread_handle, &attr, driverThread, NULL);
	if (rc) {
#ifdef DEBUG
		fprintf(stderr, "Error: return code from pthread_create() is %d\n", rc);
#endif
		return;
	}

	pthread_attr_destroy(&attr);
}



void matrixDriver_cleanup() {
	void *status;
	unsigned int rc;

	rc = pthread_mutex_lock(&driver_mutex);

	if (rc) {
		return;
	}

	driver_control_rec.active_ind = 0;
	pthread_mutex_unlock(&driver_mutex);

	// instead of joining, the driver thread can be terminated
	//pthread_cancel(driver_thread_handle);
	//pthread_cond_broadcast(&driver_ack_cond);

	pthread_join(driver_thread_handle, &status);


	pthread_cond_destroy(&driver_ack_cond);
	pthread_mutex_destroy(&driver_mutex);

	driverCleanup();
}


