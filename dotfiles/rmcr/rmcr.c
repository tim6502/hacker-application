// Remove the carriage returns (0x0d) from files
//
// performs the same transformation as:
// tr -d "\r" < src > dst
//

#include <stdio.h>
#include <stdlib.h>
#include <string.h>

void rmcr(FILE *infptr, FILE *outfptr) {
	int c;
	c = fgetc(infptr);
	while (c > -1) {
		if (c != '\r') {
			fputc(c, outfptr);
		}
		c = fgetc(infptr);
	}
}

int main(int argc, char *argv[]) {
	int i;
	char tmpfname[20];
	char rmcmd[24];

	char *datafname;
	FILE *infptr, *outfptr;
	int stat;

	if (argc <= 1) {
		printf("usage: rmcr <file1> <file2> ...\n");
		return 0;
	}

	// Test of filter
	//rmcr(stdin, stdout);

	// tmpnam is deprecated
	//tmpfname = tmpnam(0);

	memset(tmpfname, 0, 20);
	strcpy(tmpfname, "TEMP_XXXXXX");
	stat = mkstemp(tmpfname);
	if (stat < 0) {
		printf("Couldn't create temp file\n");
		return -1;
	}

	printf("Unique temp file: %s\n", tmpfname);
	for (i = 1; i < argc; i++) {
		datafname = argv[i];
		printf("Converting File: %s\n", datafname);

		infptr = fopen(datafname, "r");
		if (infptr == NULL) {
			printf("Counldn't open data file %s\n", datafname);
			return -1;
		}
		outfptr = fopen(tmpfname, "w");
		if (outfptr == NULL) {
			printf("Counldn't open temp file %s\n", tmpfname);
			return -1;
		}
		rmcr(infptr, outfptr);
		fclose(infptr);
		fclose(outfptr);

		outfptr = fopen(datafname, "w");
		infptr = fopen(tmpfname, "r");
		rmcr(infptr, outfptr);
		fclose(infptr);
		fclose(outfptr);
	}

	memset(rmcmd, 0, 24);
	sprintf(rmcmd, "rm %s", tmpfname);
	//printf("run: %s\n", rmcmd);
	system(rmcmd);

	return 0;
}

