
#include <ncurses.h>


#define ETCH_DELAY (70 * 1000)

#define ETCH_SCREEN_OFFSET_ROW 3
#define ETCH_SCREEN_OFFSET_COL 5

#define ETCH_COLOR_FRAME 1
#define ETCH_COLOR_KNOB 2
#define ETCH_COLOR_DRAW 3


// etch script constants
#define ES_END 0
#define ES_CHAR 1
#define ES_NEW_LINE 2
#define ES_PAUSE 3
#define ES_STRING 4

// script args:
// ES_CHAR, <row delta>, <col delta>, <char>
// ES_NEW_LINE, <left col>, # the left col indicates the start of the line
// ES_PAUSE, <cycles>, # pause for this number of cycles using ETCH_DELAY
// ES_STRING, <string index>, # print a string from the local string_table


unsigned char color_ind;


void drawKnob(int row, int col) {
	if (color_ind) {
		attrset(COLOR_PAIR(ETCH_COLOR_KNOB));
	}

	mvaddstr(row + 0, col, " __ ");
	mvaddstr(row + 1, col, "/  \\");
	mvaddstr(row + 2, col, "\\__/");
}


void drawEtch() {
	int i;

	if (color_ind) {
		attrset(COLOR_PAIR(ETCH_COLOR_FRAME));
	}

	mvaddstr(0, 0, "  ___________________________________________________________________________  ");
	mvaddstr(1, 0, " /  _______________________________________________________________________  \\ ");
	mvaddstr(2, 0, "/  /                                                                       \\  \\");
	for (i = 3; i <= 18; i++) {
		mvaddstr(i, 0, "|  |                                                                       |  |");
	}
	mvaddstr(19, 0, "|  \\_______________________________________________________________________/  |");
	for (i = 20; i <= 21; i++) {
		mvaddstr(i, 0, "|                                                                             |");
	}

	mvaddstr(22, 0, "\\                                                                             /");
	mvaddstr(23, 0, " \\___________________________________________________________________________/ ");

	drawKnob(20, 4);
	drawKnob(20, 71);

	refresh();
}


void etchChar(unsigned char row, unsigned char col, unsigned char c) {
	mvaddch(row + ETCH_SCREEN_OFFSET_ROW, col + ETCH_SCREEN_OFFSET_COL, c);
	refresh();
}

void etchReturnLine(unsigned char row, unsigned char start_col, unsigned char end_col, int delay) {
	unsigned char i;

	for (i = start_col; i >= end_col; i--) {
		etchChar(row, i, '_');
		usleep(delay / 2);
	}
}

void etchResume() {
	int script_nx;
	int string_nx;
	unsigned char counter;
	unsigned char row;
	unsigned char col;
	unsigned char text_col = 5;

	char *string_table[] = {
		"Tim_Graham",
		"BS_Computer_Science__University_of_Arizona",
		"05-07__C++_Systems_Developer_@_Cerner_Corp",
		"07-10__Independent_Web_Developer__Linux_Apache_MySQL_PHP",
		"10-Future__PHP_Hacker_@_Spark_Fun_"
	};

	char script[] = {

		ES_CHAR, 0, 0, '_',

		ES_PAUSE, 10,

		ES_CHAR, 0, 1, '/',
		ES_CHAR, -1, 1, '/',
		ES_CHAR, -1, 1, '_',
		ES_CHAR, 0, 1, '_',

		ES_STRING, 0,
		ES_NEW_LINE, text_col,
		ES_STRING, 1,
		ES_NEW_LINE, text_col,
		ES_STRING, 2,
		ES_NEW_LINE, text_col,
		ES_STRING, 3,
		ES_NEW_LINE, text_col,
		ES_STRING, 4,

		ES_PAUSE, 8,

		ES_CHAR, 0, 1, '?',
		ES_PAUSE, 3,
		ES_CHAR, 0, 1, '?',
		ES_PAUSE, 3,
		ES_CHAR, 0, 1, '?',
		ES_CHAR, 0, 1, '_',

		ES_PAUSE, 8,

		ES_CHAR, 0, 1, '_',
		ES_CHAR, 0, 1, '_',
		ES_CHAR, 0, 1, '_',
		ES_CHAR, 0, 1, '_',

		ES_CHAR, 0, 1, '/',
		ES_PAUSE, 1,
		ES_CHAR, 0, 1, '\\',
		ES_PAUSE, 1,
		ES_CHAR, 1, 1, '\\',
		ES_PAUSE, 1,
		ES_CHAR, 0, 1, '/',
		ES_PAUSE, 1,

		ES_CHAR, -1, 1, '/',
		ES_PAUSE, 1,
		ES_CHAR, 0, 1, '\\',
		ES_PAUSE, 1,
		ES_CHAR, 1, 1, '\\',
		ES_PAUSE, 1,
		ES_CHAR, 0, 1, '/',

		ES_CHAR, -1, 1, '_',
		ES_CHAR, 0, 1, '_',
		ES_CHAR, 0, 1, '_',
		ES_CHAR, 0, 1, '_',

		ES_CHAR, 1, 1, '|',
		ES_PAUSE, 1,
		ES_CHAR, 1, 0, '+',
		ES_PAUSE, 1,
		ES_CHAR, 0, -1, '-',
		ES_PAUSE, 3,
		ES_CHAR, 0, 2, '-',
		ES_PAUSE, 3,
		ES_CHAR, 1, -1, '+',

		ES_END
	};

	if (color_ind) {
		attrset(COLOR_PAIR(ETCH_COLOR_DRAW));
	}

	row = 2;
	col = 1;


	script_nx = 0;

	while (script[script_nx] != ES_END) {
		switch (script[script_nx]) {

		case ES_CHAR:
			row += script[script_nx + 1];
			col += script[script_nx + 2];

			etchChar(row, col, script[script_nx + 3]);
			script_nx += 3;

			usleep(ETCH_DELAY);
			break;

		case ES_PAUSE:
			counter = script[script_nx + 1];
			script_nx += 1;
			while (counter-- > 0) {
				usleep(ETCH_DELAY);
			}
			break;

		case ES_STRING:
			string_nx = script[script_nx + 1];
			script_nx += 1;

			for (counter = 0; string_table[string_nx][counter] != '\0'; counter++) {
				etchChar(row, ++col, string_table[string_nx][counter]);
				usleep(ETCH_DELAY);
			}
			break;

		case ES_NEW_LINE:
			col += 1;
			etchChar(row, col, '_');
			usleep(ETCH_DELAY);

			col += 1;
			etchChar(row, col, '_');
			usleep(ETCH_DELAY);

			row += 1;
			col += 1;
			etchChar(row, col, '|');
			usleep(ETCH_DELAY);

			col -= 1;
			etchReturnLine(row, col, script[script_nx + 1] + 1, ETCH_DELAY);
			col = script[script_nx + 1];
			script_nx += 1;

			row += 1;
			etchChar(row, col, '|');
			usleep(ETCH_DELAY);

			row += 1;
			etchChar(row, col, '|');
			usleep(ETCH_DELAY);

			col += 1;
			etchChar(row, col, '_');
			usleep(ETCH_DELAY);

			break;

		}

		script_nx++;
	}
}

int main(int argc, char *argv[]) {
	int i;

	initscr();

	// hide the cursor and keypresses
	curs_set(0);
	noecho();

	if (has_colors()) {
		color_ind = 1;
		start_color();

		// set background to black even for areas outside the drawing region
		assume_default_colors(COLOR_CYAN, COLOR_BLACK);

		init_pair(ETCH_COLOR_FRAME, COLOR_RED, COLOR_BLACK);
		init_pair(ETCH_COLOR_KNOB, COLOR_WHITE, COLOR_BLACK);
		init_pair(ETCH_COLOR_DRAW, COLOR_CYAN, COLOR_BLACK);
	} else {
		color_ind = 0;
	}


	// show the etch device
	drawEtch();

	// etch animation
	etchResume();


	// clear any pending keypresses and wait for another before quitting
	flushinp();
	getch();


	// restore the cursor and cleanup
	curs_set(1);
	endwin();

	return 1;
}


