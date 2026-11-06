import { Component, DestroyRef, OnInit, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Program, ProgramService } from '../program';

@Component({
  selector: 'app-program-list',
  imports: [],
  templateUrl: './program-list.html',
  styleUrl: './program-list.css'
})
export class ProgramList implements OnInit {
  private readonly programService = inject(ProgramService);
  private readonly destroyRef = inject(DestroyRef);

  readonly programs = signal<Program[]>([]);
  readonly loading = signal(true);
  readonly errorMessage = signal('');

  ngOnInit(): void {
    this.loadPrograms();

    this.programService.programAdded$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.loadPrograms());
  }

  loadPrograms(): void {
    this.loading.set(true);
    this.errorMessage.set('');

    this.programService.getPrograms().subscribe({
      next: (data: Program[]) => {
        this.programs.set(
          data.filter(program =>
            Boolean(program.name?.trim()) &&
            Number(program.duration) > 0
          )
        );

        this.loading.set(false);
      },
      error: (error: HttpErrorResponse) => {
        console.error('Failed to load programs:', error);

        this.errorMessage.set(
          'Unable to load programs. Please check the API connection.'
        );

        this.loading.set(false);
      }
    });
  }
}