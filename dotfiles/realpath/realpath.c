#include <stdlib.h>
#include <limits.h>
#include <stdio.h>
#include <string.h>

int main(int argc, char *argv[]) {
  char abs_path[PATH_MAX];
  char rel_path[PATH_MAX];
  memset(abs_path, 0, PATH_MAX);

  if (argc == 2) {
    realpath(argv[1], abs_path);
  } else if (argc == 1) {
    scanf("%s", rel_path);
    realpath(rel_path, abs_path);
  } else {
    printf("Usage: realpath <relative path>\n");
  }

  printf("%s\n", abs_path);

  return 0;
}

